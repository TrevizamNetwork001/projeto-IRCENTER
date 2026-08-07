<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Shared\Contracts\ClientDirectory;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateBillingContract
{
    public function __construct(
        private readonly ClientDirectory $clients,
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        int $clientId,
        array $attributes,
        array $items,
        ?int $actorUserId = null,
    ): BillingContract {
        $client = $this->clients->find($clientId);

        if (! $client) {
            throw new DomainException(
                'Cliente do IRCENTER não encontrado.'
            );
        }

        if (! $client->active) {
            throw new DomainException(
                'Cliente inativo não pode receber novo contrato financeiro.'
            );
        }

        $generationDay = (int) (
            $attributes['generation_day'] ?? 5
        );

        $dueDay = (int) (
            $attributes['due_day'] ?? 20
        );

        if ($generationDay < 1 || $generationDay > 31) {
            throw new InvalidArgumentException(
                'Dia de geração deve estar entre 1 e 31.'
            );
        }

        if ($dueDay < 1 || $dueDay > 31) {
            throw new InvalidArgumentException(
                'Dia de vencimento deve estar entre 1 e 31.'
            );
        }

        $frequency = $attributes['frequency']
            ?? BillingContract::FREQUENCY_MONTHLY;

        if (
            $frequency
            !== BillingContract::FREQUENCY_MONTHLY
        ) {
            throw new InvalidArgumentException(
                'Periodicidade ainda não suportada.'
            );
        }

        if ($items === []) {
            throw new InvalidArgumentException(
                'Contrato deve possuir ao menos um item.'
            );
        }

        $normalizedItems = [];

        foreach ($items as $position => $item) {
            $description = trim(
                (string) ($item['description'] ?? '')
            );

            if ($description === '') {
                throw new InvalidArgumentException(
                    'Descrição do item é obrigatória.'
                );
            }

            $normalizedItems[] = [
                'service_code' =>
                    $item['service_code'] ?? null,

                'description' => $description,

                'quantity' => Decimal::quantity(
                    $item['quantity'] ?? '1'
                ),

                'unit_amount' => Decimal::money(
                    $item['unit_amount'] ?? null
                ),

                'sort_order' =>
                    $item['sort_order'] ?? $position,
            ];
        }

        return DB::connection('finance_fiscal')
            ->transaction(function () use (
                $client,
                $attributes,
                $normalizedItems,
                $generationDay,
                $dueDay,
                $frequency,
                $actorUserId,
            ): BillingContract {
                $contract = BillingContract::query()->create([
                    'core_client_id' => $client->id,
                    'client_code_snapshot' => $client->clientCode,
                    'client_legal_name_snapshot' => $client->legalName,
                    'client_trade_name_snapshot' => $client->tradeName,
                    'client_document_snapshot' => $client->document,
                    'frequency' => $frequency,
                    'generation_day' => $generationDay,
                    'due_day' => $dueDay,
                    'currency' => 'BRL',
                    'status' => BillingContract::STATUS_DRAFT,

                    'billing_email_override' =>
                        $attributes[
                            'billing_email_override'
                        ] ?? null,

                    'auto_charge' => false,
                    'send_email' => false,

                    'starts_on' =>
                        $attributes['starts_on'] ?? null,

                    'ends_on' =>
                        $attributes['ends_on'] ?? null,
                ]);

                foreach ($normalizedItems as $item) {
                    $contract->items()->create([
                        ...$item,
                        'active' => true,
                    ]);
                }

                $this->audit->record(
                    module: 'finance',
                    action: 'contract.created',
                    actorUserId: $actorUserId,
                    entityType: 'billing_contract',
                    entityId: $contract->id,
                    metadata: [
                        'public_id' =>
                            $contract->public_id,

                        'core_client_id' =>
                            $contract->core_client_id,

                        'frequency' =>
                            $contract->frequency,

                        'generation_day' =>
                            $contract->generation_day,

                        'due_day' =>
                            $contract->due_day,

                        'items_count' =>
                            count($normalizedItems),
                    ],
                );

                return $contract->load('items');
            });
    }
}
