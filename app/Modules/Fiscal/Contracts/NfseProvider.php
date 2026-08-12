<?php

namespace App\Modules\Fiscal\Contracts;

use App\Modules\Fiscal\Models\FiscalDocument;

interface NfseProvider
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    public function isLive(): bool;

    public function prepare(FiscalDocument $document): array;
    public function validate(FiscalDocument $document): array;
    public function issue(FiscalDocument $document, string $idempotencyKey): array;
    public function query(FiscalDocument $document): array;
    public function cancel(FiscalDocument $document, string $idempotencyKey): array;
    public function downloadArtifacts(FiscalDocument $document): array;
}
