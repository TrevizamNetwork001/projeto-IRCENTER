<?php

namespace App\Modules\Fiscal\Actions;

use App\Modules\Finance\Support\Decimal;
use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Enums\FiscalEnvironment;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalIdempotency;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Support\Facades\DB;

final class CreateFiscalDocument
{
    public function __construct(private readonly DomainAudit $audit) {}

    public function execute(array $attributes, array $items, ?int $actorUserId = null): FiscalDocument
    {
        if (! config('finance_fiscal.fiscal.enabled', false)) {
            throw new \LogicException('Módulo fiscal está desabilitado.');
        }
        if ($items === []) {
            throw new \InvalidArgumentException('Documento fiscal deve possuir ao menos um item.');
        }

        return DB::connection('finance_fiscal')->transaction(function () use ($attributes, $items, $actorUserId): FiscalDocument {
            $services = '0.00';
            foreach ($items as &$item) {
                $item['quantity'] = Decimal::quantity($item['quantity']);
                $item['unit_amount'] = Decimal::money($item['unit_amount']);
                $item['total_amount'] = Decimal::multiplyQuantityByMoney($item['quantity'], $item['unit_amount']);
                $services = Decimal::addMoney($services, $item['total_amount']);
            }
            unset($item);

            $discount = Decimal::money($attributes['discount_amount'] ?? '0');
            $deduction = Decimal::money($attributes['deduction_amount'] ?? '0');
            $withholding = Decimal::money($attributes['withholding_amount'] ?? '0');
            $reductions = Decimal::addMoney(Decimal::addMoney($discount, $deduction), $withholding);
            if (bccomp($reductions, $services, 2) === 1) {
                throw new \InvalidArgumentException('Reduções não podem superar o valor dos serviços.');
            }

            $document = FiscalDocument::query()->create([
                ...$attributes,
                'environment' => $attributes['environment'] ?? FiscalEnvironment::Homologation,
                'provider' => $attributes['provider'] ?? config('finance_fiscal.fiscal.provider', 'fake'),
                'status' => FiscalDocumentStatus::Draft,
                'services_amount' => $services,
                'discount_amount' => $discount,
                'deduction_amount' => $deduction,
                'withholding_amount' => $withholding,
                'net_amount' => bcsub($services, $reductions, 2),
                'currency' => 'BRL',
            ]);
            $document->items()->createMany($items);
            FiscalIdempotency::query()->create(['fiscal_document_id' => $document->id, 'operation' => 'create', 'idempotency_key' => $document->idempotency_key, 'provider' => $document->provider]);
            $this->audit->record('fiscal', 'fiscal.document.created', $actorUserId, FiscalDocument::class, $document->id, ['public_id' => $document->public_id, 'environment' => $document->environment->value]);

            return $document->load(['issuer', 'customer', 'items.service']);
        });
    }
}
