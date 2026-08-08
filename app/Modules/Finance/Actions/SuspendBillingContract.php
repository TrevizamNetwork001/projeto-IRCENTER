<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingContract;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SuspendBillingContract
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
                    === BillingContract::STATUS_SUSPENDED
                ) {
                    return $contract;
                }

                if (
                    $contract->status
                    !== BillingContract::STATUS_ACTIVE
                ) {
                    throw new DomainException(
                        'Somente contrato ativo pode ser suspenso.'
                    );
                }

                $previous = $contract->status;

                $contract->status =
                    BillingContract::STATUS_SUSPENDED;

                $contract->save();

                $this->audit->record(
                    module: 'finance',
                    action: 'contract.suspended',
                    actorUserId: $actorUserId,
                    entityType: 'billing_contract',
                    entityId: $contract->id,
                    metadata: [
                        'previous_status' => $previous,
                        'status' =>
                            BillingContract::STATUS_SUSPENDED,
                    ],
                );

                return $contract->refresh();
            });
    }
}
