<?php

namespace App\Http\Controllers;

use App\Modules\Finance\Actions\CreateChargeForInvoice;
use App\Modules\Finance\Actions\ReconcileChargeSubmission;
use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

final class ChargeController extends Controller
{
    public function __construct(
        private readonly PaymentProvider $provider,
    ) {
    }

    public function store(
        Request $request,
        Invoice $invoice,
        CreateChargeForInvoice $action,
    ): RedirectResponse {
        $this->authorizeMutation();

        /*
         * Trava Web adicional.
         *
         * Enquanto já existir uma cobrança operacional
         * ou incerta para a fatura, a interface não
         * permite uma nova submissão, mesmo com outro
         * método/provider.
         *
         * A regra de domínio existente de idempotência
         * continua preservada abaixo desta camada.
         */
        $blockingCharge =
            $this->blockingChargeForInvoice(
                $invoice
            );

        if ($blockingCharge) {
            $uncertain = in_array(
                $blockingCharge->status,
                [
                    Charge::STATUS_SUBMITTING,
                    Charge::STATUS_SUBMISSION_UNKNOWN,
                ],
                true
            );

            return back()->with(
                'error',
                $uncertain
                    ? 'Já existe uma cobrança em estado '
                        .'incerto para esta fatura. '
                        .'Nenhuma nova submissão foi '
                        .'realizada. Use a reconciliação '
                        .'quando disponível.'
                    : 'Já existe uma cobrança ativa para '
                        .'esta fatura. Nenhuma nova '
                        .'submissão foi realizada.'
            );
        }

        $methods = $this->availableMethods();

        if ($methods === []) {
            return back()->with(
                'error',
                'Provider configurado não oferece método '
                .'de cobrança compatível.'
            );
        }

        $data = $request->validate([
            'method' => [
                'required',
                'string',
                Rule::in($methods),
            ],
        ]);

        /*
         * Fake é puramente local.
         *
         * Qualquer provider externo exige uma
         * confirmação explícita da operação.
         */
        if ($this->provider->key() !== 'fake') {
            $request->validate([
                'confirm_provider_submission' => [
                    'accepted',
                ],
            ], [
                'confirm_provider_submission.accepted' =>
                    'Confirme explicitamente a emissão '
                    .'no ambiente do provider.',
            ]);
        }

        /*
         * Efí exige confirmação forte adicional.
         *
         * A frase não contém segredo. Ela serve apenas
         * como barreira contra clique acidental.
         */
        if ($this->provider->key() === 'efi') {
            $request->validate([
                'confirm_provider_phrase' => [
                    'required',
                    'string',
                    Rule::in([
                        'EMITIR EFI HOMOLOGACAO',
                    ]),
                ],
            ], [
                'confirm_provider_phrase.required' =>
                    'Digite a frase de confirmação.',

                'confirm_provider_phrase.in' =>
                    'Digite exatamente '
                    .'"EMITIR EFI HOMOLOGACAO".',
            ]);
        }

        try {
            $charge = $action->handle(
                $invoice->id,
                $data['method'],
                auth()->id(),
            );
        } catch (
            DomainException|InvalidArgumentException $exception
        ) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        } catch (Throwable) {
            /*
             * Nunca mostramos mensagem/resposta bruta
             * do provider na interface.
             *
             * CreateChargeForInvoice já transforma
             * incerteza externa em submission_unknown
             * sem novo POST automático.
             */
            return redirect()
                ->route(
                    'finance.invoices.show',
                    $invoice
                )
                ->with(
                    'error',
                    'A submissão não pôde ser confirmada. '
                    .'A cobrança pode ter sido criada no '
                    .'provider. Não tente emitir novamente; '
                    .'verifique o estado e use a '
                    .'reconciliação quando disponível.'
                );
        }

        $message = in_array(
            $charge->status,
            [
                Charge::STATUS_SUBMITTING,
                Charge::STATUS_SUBMISSION_UNKNOWN,
            ],
            true
        )
            ? 'Já existe uma cobrança em estado incerto. '
                .'Nenhuma nova submissão foi realizada.'
            : 'Cobrança disponível.';

        return redirect()
            ->route(
                'finance.invoices.show',
                $invoice
            )
            ->withInput([
                'method' => $data['method'],
            ])
            ->with(
                'success',
                $message
            );
    }

    public function reconcile(
        Charge $charge,
        ReconcileChargeSubmission $action,
    ): RedirectResponse {
        $this->authorizeMutation();

        if (
            ! $this->provider
                instanceof CorrelatablePaymentProvider
            || $this->provider->key() === 'fake'
        ) {
            return $this->toInvoice(
                $charge,
                'error',
                'O provider atual não possui '
                .'reconciliação manual disponível.'
            );
        }

        if (
            $charge->provider
            !== $this->provider->key()
        ) {
            return $this->toInvoice(
                $charge,
                'error',
                'A cobrança pertence a outro provider.'
            );
        }

        try {
            $result = $action->handle(
                $charge->id,
                auth()->id(),
            );
        } catch (DomainException $exception) {
            return $this->toInvoice(
                $charge,
                'error',
                $exception->getMessage()
            );
        } catch (Throwable) {
            return $this->toInvoice(
                $charge,
                'error',
                'A reconciliação não pôde ser '
                .'concluída. Nenhuma nova cobrança '
                .'foi criada.'
            );
        }

        if (
            in_array(
                $result->status,
                [
                    Charge::STATUS_SUBMITTING,
                    Charge::STATUS_SUBMISSION_UNKNOWN,
                ],
                true
            )
        ) {
            return $this->toInvoice(
                $result,
                'error',
                'Nenhuma cobrança correspondente foi '
                .'confirmada. O estado permanece incerto.'
            );
        }

        return $this->toInvoice(
            $result,
            'success',
            'Cobrança reconciliada com o provider.'
        );
    }

    private function blockingChargeForInvoice(
        Invoice $invoice,
    ): ?Charge {
        return Charge::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->whereIn(
                'status',
                [
                    Charge::STATUS_SUBMITTING,
                    Charge::STATUS_SUBMISSION_UNKNOWN,
                    Charge::STATUS_CREATED,
                    Charge::STATUS_OPEN,
                    Charge::STATUS_PAID,
                    Charge::STATUS_OVERDUE,
                ]
            )
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function availableMethods(): array
    {
        return array_values(
            array_intersect(
                [
                    Charge::METHOD_BOLETO,
                    Charge::METHOD_PIX,
                    Charge::METHOD_BOLETO_PIX,
                ],
                $this->provider->capabilities()
            )
        );
    }

    private function authorizeMutation(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );

        abort_unless(
            config(
                'finance_fiscal.finance.enabled',
                false
            ),
            503,
            'Módulo financeiro está desabilitado.'
        );

        /*
         * A Web Efí desta fase é EXCLUSIVAMENTE
         * homologação.
         *
         * Mesmo um ambiente desconhecido/futuro deve
         * permanecer bloqueado até ser revisado.
         */
        if ($this->provider->key() === 'efi') {
            abort_unless(
                config(
                    'finance_fiscal.providers.'
                    .'efi.environment'
                ) === 'homologation',
                503,
                'Efí pela interface Web está liberada '
                .'somente em homologação.'
            );
        }

        /*
         * Trava independente contra provider live.
         *
         * Mesmo que PAYMENT_LIVE_ENABLED seja ligado
         * por engano, a Web continua bloqueada.
         */
        abort_if(
            $this->provider->isLive(),
            503,
            'Cobrança live pela interface Web '
            .'ainda não foi liberada.'
        );
    }

    private function toInvoice(
        Charge $charge,
        string $type,
        string $message,
    ): RedirectResponse {
        return redirect()
            ->route(
                'finance.invoices.show',
                $charge->invoice_id
            )
            ->with(
                $type,
                $message
            );
    }
}
