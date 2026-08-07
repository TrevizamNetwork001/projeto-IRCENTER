<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingContract;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ActivateBillingContract
{
    public function __construct(
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        int $contractId,
        ?int $actorUserId = null,
    ): BillingContract {
        return DB::connection('finance_fiscal')
            ->transaction(function () use (
                $contractId,
                $actorUserId,
            ): BillingContract {
                $contract = BillingContract::query()
                    ->whereKey($contractId)
                    ->lockForUpdate()
                    ->first();

                if (! $contract) {
                    throw new DomainException(
                        'Contrato financeiro não encontrado.'
                    );
                }

                if (
                    $contract->status
                    === BillingContract::STATUS_ACTIVE
                ) {
                    return $contract;
                }

                if (
                    $contract->status
                    === BillingContract::STATUS_ENDED
                ) {
                    throw new DomainException(
                        'Contrato encerrado não pode ser ativado.'
                    );
                }

                if (
                    ! $contract->items()
                        ->where('active', true)
                        ->exists()
                ) {
                    throw new DomainException(
                        'Contrato não possui itens ativos.'
                    );
                }

                $previous = $contract->status;

                $contract->status =
                    BillingContract::STATUS_ACTIVE;

                $contract->save();

                $this->audit->record(
                    module: 'finance',
                    action: 'contract.activated',
                    actorUserId: $actorUserId,
                    entityType: 'billing_contract',
                    entityId: $contract->id,
                    metadata: [
                        'previous_status' => $previous,
                        'status' =>
                            BillingContract::STATUS_ACTIVE,
                    ],
                );

                return $contract->refresh();
            });
    }
}
