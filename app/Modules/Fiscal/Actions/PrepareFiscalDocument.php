<?php

namespace App\Modules\Fiscal\Actions;

use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Support\Facades\DB;

final class PrepareFiscalDocument
{
    public function __construct(private readonly DomainAudit $audit) {}

    public function execute(FiscalDocument $document, ?int $actorUserId = null): FiscalDocument
    {
        if (! config('finance_fiscal.fiscal.enabled', false)) {
            throw new \LogicException('Módulo fiscal está desabilitado.');
        }

        return DB::connection('finance_fiscal')->transaction(function () use ($document, $actorUserId): FiscalDocument {
            $document->loadMissing(['issuer', 'customer', 'items.service']);
            $document->transitionTo(FiscalDocumentStatus::Ready);
            $document->forceFill([
                'issuer_snapshot' => $document->issuer->snapshot(),
                'customer_snapshot' => $document->customer->snapshot(),
                'service_snapshot' => $document->items->map(fn ($item) => ['item_id' => $item->id, 'description' => $item->description, 'quantity' => $item->quantity, 'unit_amount' => $item->unit_amount, 'total_amount' => $item->total_amount, 'classification' => $item->service?->snapshot() ?? $item->classification_snapshot])->all(),
                'values_snapshot' => $document->only(['services_amount', 'discount_amount', 'deduction_amount', 'withholding_amount', 'net_amount', 'currency']),
                'tax_snapshot' => ['issuer' => $document->issuer->tax_settings ?? [], 'items' => $document->items->map(fn ($item) => $item->tax_snapshot ?? $item->service?->tax_settings ?? [])->all()],
                'prepared_at' => now(),
            ])->save();
            $this->audit->record('fiscal', 'document.ready', $actorUserId, FiscalDocument::class, $document->id, ['public_id' => $document->public_id]);
            return $document->refresh();
        });
    }
}
