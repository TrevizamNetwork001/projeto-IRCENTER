<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Contracts\PreflightsPaymentCharges;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class CreateChargeForInvoice
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        int $invoiceId,
        string $method,
        ?int $actorUserId = null,
    ): Charge {
        $this->assertFinanceEnabled();
        $this->assertMethodSupported($method);
        $this->assertLiveAllowed();

        $preflightInvoice = Invoice::query()->whereKey($invoiceId)->first();

        if (! $preflightInvoice) {
            throw new DomainException('Fatura não encontrada.');
        }

        $idempotencyKey = $this->idempotencyKey($preflightInvoice, $method);
        $preflightRequest = $this->makeRequest($preflightInvoice, $idempotencyKey, $method);

        if ($this->provider instanceof PreflightsPaymentCharges) {
            $this->provider->preflightCharge($preflightRequest);
        }

        /*
         * FASE 1:
         *
         * Reserva a cobrança localmente em uma transação
         * curta, antes de qualquer chamada externa.
         *
         * A partir de STATUS_SUBMITTING nunca repetimos
         * automaticamente o POST ao provider.
         */
        $reservation =
            DB::connection('finance_fiscal')
                ->transaction(
                    function () use (
                        $invoiceId,
                        $method,
                        $actorUserId,
                    ): array {
                        $invoice = Invoice::query()
                            ->whereKey($invoiceId)
                            ->lockForUpdate()
                            ->first();

                        if (! $invoice) {
                            throw new DomainException(
                                'Fatura não encontrada.'
                            );
                        }

                        if (
                            $invoice->status
                            !== Invoice::STATUS_OPEN
                        ) {
                            throw new DomainException(
                                'Somente fatura aberta '
                                .'pode gerar cobrança.'
                            );
                        }

                        $idempotencyKey = $this->idempotencyKey($invoice, $method);

                        $blocking = Charge::query()
                            ->where('invoice_id', $invoice->id)
                            ->whereIn('status', Charge::blockingStatuses())
                            ->lockForUpdate()
                            ->first();

                        if ($blocking) {
                            return ['charge' => $blocking, 'request' => null, 'submit' => false];
                        }

                        $existing = Charge::query()
                            ->where(
                                'idempotency_key',
                                $idempotencyKey,
                            )
                            ->lockForUpdate()
                            ->first();

                        if ($existing) {
                            return [
                                'charge' => $existing,
                                'request' => null,
                                'submit' => false,
                            ];
                        }

                        $request =
                            $this->makeRequest(
                                $invoice,
                                $idempotencyKey,
                                $method,
                            );

                        $charge =
                            Charge::query()->create([
                                'invoice_id' =>
                                    $invoice->id,

                                'provider' =>
                                    $this->provider->key(),

                                'method' =>
                                    $method,

                                'status' =>
                                    Charge::
                                        STATUS_SUBMITTING,

                                'idempotency_key' =>
                                    $idempotencyKey,

                                'provider_charge_id' =>
                                    null,

                                'amount' =>
                                    Decimal::money(
                                        $invoice->total
                                    ),

                                'currency' =>
                                    $invoice->currency,

                                'due_on' =>
                                    $invoice->due_on
                                        ->toDateString(),

                                'provider_checkout_url' =>
                                    null,

                                'provider_billet_url' =>
                                    null,

                                'provider_billet_pdf_url' =>
                                    null,

                                'provider_barcode' =>
                                    null,

                                'provider_pix_copy_paste' =>
                                    null,

                                'provider_created_at' =>
                                    null,

                                'last_synced_at' =>
                                    null,
                            ]);

                        $this->audit->record(
                            module: 'finance',

                            action:
                                'charge.submission_reserved',

                            actorUserId:
                                $actorUserId,

                            entityType:
                                'charge',

                            entityId:
                                $charge->id,

                            metadata: [
                                'public_id' =>
                                    $charge->public_id,

                                'invoice_id' =>
                                    $invoice->id,

                                'provider' =>
                                    $charge->provider,

                                'method' =>
                                    $charge->method,

                                'status' =>
                                    $charge->status,

                                'amount' =>
                                    $charge->amount,
                            ],
                        );

                        return [
                            'charge' => $charge,
                            'request' => $request,
                            'submit' => true,
                        ];
                    }
                );

        /** @var Charge $charge */
        $charge = $reservation['charge'];

        /*
         * Idempotência local:
         *
         * Se já existe qualquer cobrança para a chave,
         * inclusive submitting/submission_unknown,
         * nunca chamamos o provider novamente aqui.
         */
        if (! $reservation['submit']) {
            return $charge->fresh();
        }

        /** @var PaymentChargeRequest $request */
        $request = $reservation['request'];

        /*
         * FASE 2:
         *
         * A chamada HTTPS ocorre deliberadamente FORA
         * da transação financeira.
         */
        try {
            $result =
                $this->provider->createCharge(
                    $request
                );
        } catch (Throwable $exception) {
            /*
             * Se houve exceção após o início da
             * submissão, não sabemos com segurança se
             * o provider chegou ou não a criar a
             * cobrança.
             *
             * Nunca fazemos retry automático.
             */
            DB::connection('finance_fiscal')
                ->transaction(
                    function () use (
                        $charge,
                        $actorUserId,
                        $exception,
                    ): void {
                        $locked =
                            Charge::query()
                                ->whereKey(
                                    $charge->id
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        if (
                            $locked
                                ->provider_charge_id
                            === null
                            && $locked->status
                                === Charge::
                                    STATUS_SUBMITTING
                        ) {
                            $locked->update([
                                'status' =>
                                    Charge::
                                        STATUS_SUBMISSION_UNKNOWN,
                            ]);

                            $diagnostics =
                                $this->failureDiagnostics(
                                    $exception
                                );

                            $this->audit->record(
                                module:
                                    'finance',

                                action:
                                    'charge.submission_unknown',

                                actorUserId:
                                    $actorUserId,

                                entityType:
                                    'charge',

                                entityId:
                                    $locked->id,

                                metadata: [
                                    'public_id' =>
                                        $locked
                                            ->public_id,

                                    'provider' =>
                                        $locked
                                            ->provider,

                                    'method' =>
                                        $locked
                                            ->method,

                                    ...$diagnostics,
                                ],
                            );

                            Log::warning(
                                'Falha ao submeter cobrança ao provider.',
                                [
                                    'operation' =>
                                        'finance.charge.submit',

                                    'module' => 'finance',

                                    'charge_id' =>
                                        $locked->id,

                                    'invoice_id' =>
                                        $locked->invoice_id,

                                    'provider' =>
                                        $locked->provider,

                                    'method' =>
                                        $locked->method,

                                    'status' => 'unknown',

                                    ...$diagnostics,
                                ],
                            );
                        }
                    }
                );

            throw $exception;
        }

        /*
         * FASE 3:
         *
         * Após sucesso externo, apenas persiste o
         * resultado em uma segunda transação curta.
         *
         * Se esta transação falhar, a Charge continuará
         * como submitting e também NÃO será reenviada
         * automaticamente.
         */
        return DB::connection('finance_fiscal')
            ->transaction(
                function () use (
                    $charge,
                    $result,
                    $actorUserId,
                ): Charge {
                    $locked =
                        Charge::query()
                            ->whereKey(
                                $charge->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                     * Proteção defensiva caso algum
                     * fluxo já tenha reconciliado a
                     * cobrança.
                     */
                    if (
                        $locked->provider_charge_id
                        !== null
                    ) {
                        return $locked;
                    }

                    $locked->update([
                        'status' =>
                            $result->status,

                        'provider_charge_id' =>
                            $result
                                ->providerChargeId,

                        'provider_checkout_url' =>
                            $result
                                ->checkoutUrl,

                        'provider_billet_url' =>
                            $result
                                ->billetUrl,

                        'provider_billet_pdf_url' =>
                            $result
                                ->billetPdfUrl,

                        'provider_barcode' =>
                            $result
                                ->barcode,

                        'provider_pix_copy_paste' =>
                            $result
                                ->pixCopyPaste,

                        'provider_created_at' =>
                            now(),

                        'last_synced_at' =>
                            now(),
                    ]);

                    $this->audit->record(
                        module: 'finance',

                        action:
                            'charge.created',

                        actorUserId:
                            $actorUserId,

                        entityType:
                            'charge',

                        entityId:
                            $locked->id,

                        metadata: [
                            'public_id' =>
                                $locked->public_id,

                            'invoice_id' =>
                                $locked->invoice_id,

                            'provider' =>
                                $locked->provider,

                            'method' =>
                                $locked->method,

                            'status' =>
                                $locked->status,

                            'amount' =>
                                $locked->amount,
                        ],
                    );

                    return $locked;
                }
            );
    }

    private function assertFinanceEnabled(): void
    {
        if (
            ! config(
                'finance_fiscal.finance.enabled',
                false
            )
        ) {
            throw new DomainException(
                'Módulo financeiro está desabilitado.'
            );
        }
    }

    private function assertMethodSupported(
        string $method,
    ): void {
        if (
            ! in_array(
                $method,
                [
                    Charge::METHOD_BOLETO,
                    Charge::METHOD_PIX,
                    Charge::METHOD_BOLETO_PIX,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Método de cobrança não suportado.'
            );
        }

        if (
            ! in_array(
                $method,
                $this->provider->capabilities(),
                true
            )
        ) {
            throw new DomainException(
                'Provider não suporta o método solicitado.'
            );
        }
    }

    private function assertLiveAllowed(): void
    {
        if (
            $this->provider->isLive()
            && ! config(
                'finance_fiscal.finance.'
                .'payment_live_enabled',
                false
            )
        ) {
            throw new DomainException(
                'Cobrança live está desabilitada.'
            );
        }
    }

    private function makeRequest(
        Invoice $invoice,
        string $idempotencyKey,
        string $method,
    ): PaymentChargeRequest {
        return new PaymentChargeRequest(
            invoicePublicId:
                $invoice->public_id,

            idempotencyKey:
                $idempotencyKey,

            method:
                $method,

            amount:
                Decimal::money(
                    $invoice->total
                ),

            currency:
                $invoice->currency,

            dueOn:
                $invoice->due_on
                    ->toDateString(),

            payerName:
                $invoice
                    ->client_legal_name_snapshot,

            payerDocument:
                $invoice
                    ->client_document_snapshot,

            payerEmail:
                $invoice
                    ->billing_email_snapshot,

            payerPhone:
                $invoice
                    ->client_phone_snapshot,

            payerPostalCode:
                $invoice
                    ->client_postal_code_snapshot,

            payerStreet:
                $invoice
                    ->client_street_snapshot,

            payerAddressNumber:
                $invoice
                    ->client_address_number_snapshot,

            payerAddressComplement:
                $invoice
                    ->client_address_complement_snapshot,

            payerDistrict:
                $invoice
                    ->client_district_snapshot,

            payerCity:
                $invoice
                    ->client_city_snapshot,

            payerState:
                $invoice
                    ->client_state_snapshot,

            payerCountry:
                $invoice
                    ->client_country_snapshot,
        );
    }

    private function idempotencyKey(Invoice $invoice, string $method): string
    {
        return sprintf(
            'invoice:%s:provider:%s:method:%s',
            $invoice->public_id,
            $this->provider->key(),
            $method,
        );
    }

    /** @return array<string, int|string|null> */
    private function failureDiagnostics(
        Throwable $exception,
    ): array {
        $diagnostics = [
            'error_type' => $exception::class,
            'http_status' => null,
            'remote_error_code' => null,
        ];

        if (! $exception instanceof RequestException) {
            return $diagnostics;
        }

        $response = $exception->response;

        if ($response === null) {
            return $diagnostics;
        }

        $diagnostics['http_status'] = $response->status();

        foreach (['code', 'error_code'] as $key) {
            $candidate = $response->json($key);

            if (
                (is_int($candidate) || is_string($candidate))
                && preg_match(
                    '/^[A-Za-z0-9._:-]{1,80}$/',
                    (string) $candidate,
                ) === 1
            ) {
                $diagnostics['remote_error_code'] =
                    (string) $candidate;

                break;
            }
        }

        return $diagnostics;
    }
}
