<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Models\Charge;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReconcileChargeSubmission
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        int $chargeId,
        ?int $actorUserId = null,
    ): Charge {
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

        if (
            ! $this->provider
                instanceof CorrelatablePaymentProvider
        ) {
            throw new DomainException(
                'Provider não suporta reconciliação '
                .'por correlação.'
            );
        }

        $charge = Charge::query()
            ->whereKey($chargeId)
            ->first();

        if (! $charge) {
            throw new DomainException(
                'Cobrança não encontrada.'
            );
        }

        if (
            $charge->provider
            !== $this->provider->key()
        ) {
            throw new DomainException(
                'Provider da cobrança não corresponde '
                .'ao provider configurado.'
            );
        }

        /*
         * Reconciliação é idempotente.
         * Uma cobrança já resolvida simplesmente
         * retorna como está.
         */
        if (
            ! in_array(
                $charge->status,
                [
                    Charge::STATUS_SUBMITTING,
                    Charge::STATUS_SUBMISSION_UNKNOWN,
                ],
                true
            )
        ) {
            return $charge;
        }

        if (
            $charge->provider_charge_id
            !== null
        ) {
            return $charge;
        }

        /*
         * Janela deliberadamente pequena em torno
         * da criação local.
         *
         * Não usamos "hoje", pois uma cobrança pode
         * ser reconciliada dias depois.
         */
        $createdAt = Carbon::parse(
            $charge->created_at
        );

        $beginDate = $createdAt
            ->copy()
            ->subDays(2)
            ->toDateString();

        $endDate = $createdAt
            ->copy()
            ->addDays(2)
            ->toDateString();

        /*
         * CHAMADA EXTERNA FORA DE TRANSAÇÃO.
         *
         * Este método executa apenas buscas.
         * Nunca cria uma nova cobrança.
         */
        $result =
            $this->provider
                ->findChargeByCorrelation(
                    $charge->idempotency_key,
                    $beginDate,
                    $endDate,
                );

        if ($result === null) {
            return DB::connection(
                'finance_fiscal'
            )->transaction(
                function () use (
                    $charge,
                    $actorUserId,
                    $beginDate,
                    $endDate,
                ): Charge {
                    $locked = Charge::query()
                        ->whereKey($charge->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        ! in_array(
                            $locked->status,
                            [
                                Charge::STATUS_SUBMITTING,
                                Charge::
                                    STATUS_SUBMISSION_UNKNOWN,
                            ],
                            true
                        )
                    ) {
                        return $locked;
                    }

                    /*
                     * Depois de uma tentativa explícita
                     * de reconciliação sem resultado,
                     * "submitting" passa a representar
                     * corretamente um estado incerto.
                     */
                    if (
                        $locked->status
                        === Charge::STATUS_SUBMITTING
                    ) {
                        $locked->status =
                            Charge::
                                STATUS_SUBMISSION_UNKNOWN;
                    }

                    $locked->last_synced_at =
                        now();

                    $locked->save();

                    $this->audit->record(
                        module: 'finance',

                        action:
                            'charge.reconciliation_not_found',

                        actorUserId:
                            $actorUserId,

                        entityType:
                            'charge',

                        entityId:
                            $locked->id,

                        metadata: [
                            'public_id' =>
                                $locked->public_id,

                            'provider' =>
                                $locked->provider,

                            'status' =>
                                $locked->status,

                            'begin_date' =>
                                $beginDate,

                            'end_date' =>
                                $endDate,
                        ],
                    );

                    return $locked;
                }
            );
        }

        return DB::connection(
            'finance_fiscal'
        )->transaction(
            function () use (
                $charge,
                $result,
                $actorUserId,
            ): Charge {
                $locked = Charge::query()
                    ->whereKey($charge->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Pode ter sido reconciliada por webhook
                 * ou outra execução enquanto fazíamos
                 * o GET externo.
                 */
                if (
                    ! in_array(
                        $locked->status,
                        [
                            Charge::STATUS_SUBMITTING,
                            Charge::
                                STATUS_SUBMISSION_UNKNOWN,
                        ],
                        true
                    )
                ) {
                    return $locked;
                }

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
                        $result->providerChargeId,

                    'provider_checkout_url' =>
                        $result->checkoutUrl,

                    'provider_pix_copy_paste' =>
                        $result->pixCopyPaste,

                    'provider_created_at' =>
                        $locked->provider_created_at
                        ?? now(),

                    'last_synced_at' =>
                        now(),
                ]);

                $this->audit->record(
                    module: 'finance',

                    action:
                        'charge.reconciled',

                    actorUserId:
                        $actorUserId,

                    entityType:
                        'charge',

                    entityId:
                        $locked->id,

                    metadata: [
                        'public_id' =>
                            $locked->public_id,

                        'provider' =>
                            $locked->provider,

                        'method' =>
                            $locked->method,

                        'status' =>
                            $locked->status,

                        'provider_charge_id' =>
                            $locked
                                ->provider_charge_id,
                    ],
                );

                return $locked;
            }
        );
    }
}
