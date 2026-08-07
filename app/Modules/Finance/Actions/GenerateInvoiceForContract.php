<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Shared\Services\DomainAudit;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GenerateInvoiceForContract
{
    public function __construct(
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        int $contractId,
        string $competence,
        ?int $actorUserId = null,
    ): Invoice {
        if (
            ! preg_match(
                '/^\d{4}-(0[1-9]|1[0-2])$/',
                $competence
            )
        ) {
            throw new InvalidArgumentException(
                'Competência deve usar o formato YYYY-MM.'
            );
        }

        $timezone = config(
            'finance_fiscal.timezone',
            'America/Sao_Paulo'
        );

        $month = CarbonImmutable::createFromFormat(
            '!Y-m',
            $competence,
            $timezone
        );

        return DB::connection('finance_fiscal')
            ->transaction(function () use (
                $contractId,
                $competence,
                $actorUserId,
                $timezone,
                $month,
            ): Invoice {
                $contract = BillingContract::query()
                    ->whereKey($contractId)
                    ->lockForUpdate()
                    ->first();

                if (! $contract) {
                    throw new DomainException(
                        'Contrato financeiro não encontrado.'
                    );
                }

                $generationKey = sprintf(
                    'contract:%s:competence:%s',
                    $contract->public_id,
                    $competence
                );

                /*
                 * A chave canônica é a fonte da idempotência.
                 *
                 * Evita depender da representação interna de
                 * DATE entre SQLite e PostgreSQL.
                 */
                $existing = Invoice::query()
                    ->where(
                        'generation_key',
                        $generationKey
                    )
                    ->first();

                if ($existing) {
                    return $existing->load('items');
                }

                if (
                    $contract->status
                    !== BillingContract::STATUS_ACTIVE
                ) {
                    throw new DomainException(
                        'Contrato precisa estar ativo.'
                    );
                }

                $items = $contract->items()
                    ->where('active', true)
                    ->get();

                if ($items->isEmpty()) {
                    throw new DomainException(
                        'Contrato não possui itens ativos.'
                    );
                }

                $subtotal = '0.00';
                $invoiceItems = [];

                foreach ($items as $item) {
                    $lineTotal =
                        Decimal::multiplyQuantityByMoney(
                            $item->quantity,
                            $item->unit_amount,
                        );

                    $subtotal = Decimal::addMoney(
                        $subtotal,
                        $lineTotal
                    );

                    $invoiceItems[] = [
                        'billing_contract_item_id' =>
                            $item->id,

                        'service_code' =>
                            $item->service_code,

                        'description' =>
                            $item->description,

                        'quantity' =>
                            $item->quantity,

                        'unit_amount' =>
                            $item->unit_amount,

                        'line_total' =>
                            $lineTotal,

                        'sort_order' =>
                            $item->sort_order,
                    ];
                }

                $dueDay = min(
                    $contract->due_day,
                    $month->daysInMonth
                );

                $dueOn = $month
                    ->setDay($dueDay)
                    ->toDateString();

                $invoice = Invoice::query()->create([
                    'billing_contract_id' =>
                        $contract->id,

                    'core_client_id' =>
                        $contract->core_client_id,

                    'client_code_snapshot' =>
                        $contract->client_code_snapshot,

                    'client_legal_name_snapshot' =>
                        $contract
                            ->client_legal_name_snapshot,

                    'client_trade_name_snapshot' =>
                        $contract
                            ->client_trade_name_snapshot,

                    'client_document_snapshot' =>
                        $contract
                            ->client_document_snapshot,

                    'source' =>
                        Invoice::SOURCE_RECURRING,

                    'competence_month' =>
                        $month->toDateString(),

                    'generation_key' =>
                        $generationKey,

                    'issued_on' =>
                        CarbonImmutable::now(
                            $timezone
                        )->toDateString(),

                    'due_on' => $dueOn,

                    'currency' =>
                        $contract->currency,

                    'status' =>
                        Invoice::STATUS_OPEN,

                    'subtotal' => $subtotal,
                    'discount' => '0.00',
                    'total' => $subtotal,
                ]);

                foreach ($invoiceItems as $item) {
                    $invoice->items()->create($item);
                }

                $this->audit->record(
                    module: 'finance',
                    action: 'invoice.generated',
                    actorUserId: $actorUserId,
                    entityType: 'invoice',
                    entityId: $invoice->id,
                    metadata: [
                        'public_id' =>
                            $invoice->public_id,

                        'billing_contract_id' =>
                            $contract->id,

                        'competence' =>
                            $competence,

                        'generation_key' =>
                            $generationKey,

                        'total' =>
                            $subtotal,

                        'items_count' =>
                            count($invoiceItems),
                    ],
                );

                return $invoice->load('items');
            });
    }
}
