<?php

namespace App\Modules\Fiscal\Actions;

use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Shared\Services\DomainAudit;

final class AuthorizeFiscalDocumentManually
{
    public function __construct(private readonly DomainAudit $audit) {}

    public function execute(FiscalDocument $document, array $data, int $actorUserId): FiscalDocument
    {
        $document->transitionTo(FiscalDocumentStatus::Authorized);
        $document->fill(['nfse_number' => $data['nfse_number'], 'access_key' => $data['access_key'] ?? null, 'verification_code' => $data['verification_code'] ?? null, 'authorized_at' => $data['authorized_at'], 'manual_authorization_notes' => $data['manual_authorization_notes'] ?? null, 'emission_origin' => 'manual', 'registered_by_user_id' => $actorUserId])->save();
        $this->audit->record('fiscal', 'fiscal.document.manual_authorized', $actorUserId, FiscalDocument::class, $document->id, ['public_id' => $document->public_id, 'nfse_number' => $document->nfse_number, 'origin' => 'manual']);
        return $document->refresh();
    }
}
