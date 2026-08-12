<?php

namespace App\Modules\Fiscal\Actions;

use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Shared\Services\DomainAudit;

final class ReturnFiscalDocumentToDraft
{
    public function __construct(private readonly DomainAudit $audit) {}

    public function execute(FiscalDocument $document, int $actorUserId): FiscalDocument
    {
        $document->transitionTo(FiscalDocumentStatus::Draft);
        $document->forceFill(['issuer_snapshot' => null, 'customer_snapshot' => null, 'service_snapshot' => null, 'values_snapshot' => null, 'tax_snapshot' => null, 'prepared_at' => null])->save();
        $this->audit->record('fiscal', 'fiscal.document.returned_to_draft', $actorUserId, FiscalDocument::class, $document->id, ['public_id' => $document->public_id]);
        return $document->refresh();
    }
}
