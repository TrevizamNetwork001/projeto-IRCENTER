<?php

namespace App\Modules\Fiscal\Infrastructure;

use App\Modules\Fiscal\Contracts\NfseProvider;
use App\Modules\Fiscal\Models\FiscalDocument;

final class FakeNfseProvider implements NfseProvider
{
    public function key(): string
    {
        return 'fake';
    }

    public function capabilities(): array
    {
        return [
            'draft',
            'review',
            'transmit',
            'consult',
            'cancel',
            'substitute',
            'xml',
            'danfse',
        ];
    }

    public function isLive(): bool
    {
        return false;
    }

    public function prepare(FiscalDocument $document): array { return ['prepared' => true, 'provider' => $this->key()]; }
    public function validate(FiscalDocument $document): array { return ['valid' => true, 'errors' => []]; }
    public function issue(FiscalDocument $document, string $idempotencyKey): array { return ['accepted' => true, 'simulated' => true, 'idempotency_key' => $idempotencyKey]; }
    public function query(FiscalDocument $document): array { return ['status' => $document->status->value, 'simulated' => true]; }
    public function cancel(FiscalDocument $document, string $idempotencyKey): array { return ['cancelled' => true, 'simulated' => true, 'idempotency_key' => $idempotencyKey]; }
    public function downloadArtifacts(FiscalDocument $document): array { return []; }
}
