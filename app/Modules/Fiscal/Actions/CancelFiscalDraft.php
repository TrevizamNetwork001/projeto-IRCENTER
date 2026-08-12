<?php

namespace App\Modules\Fiscal\Actions;

use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Shared\Services\DomainAudit;

final class CancelFiscalDraft
{
    public function __construct(private readonly DomainAudit $audit) {}
    public function execute(FiscalDocument $document, int $actorUserId): FiscalDocument
    {
        $document->transitionTo(FiscalDocumentStatus::Cancelled);
        $document->cancelled_at = now();
        $document->save();
        $this->audit->record('fiscal', 'fiscal.document.internal_cancelled', $actorUserId, FiscalDocument::class, $document->id, ['public_id' => $document->public_id, 'government_operation' => false]);
        return $document->refresh();
    }
}
