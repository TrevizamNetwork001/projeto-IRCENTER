<?php

namespace App\Modules\Fiscal\Infrastructure;

use App\Modules\Fiscal\Contracts\NfseProvider;
use App\Modules\Fiscal\Models\FiscalDocument;
use LogicException;

final class DisabledNfseProvider implements NfseProvider
{
    public function key(): string { return 'disabled'; }
    public function capabilities(): array { return []; }
    public function isLive(): bool { return false; }
    public function prepare(FiscalDocument $document): array { return $this->blocked(); }
    public function validate(FiscalDocument $document): array { return $this->blocked(); }
    public function issue(FiscalDocument $document, string $idempotencyKey): array { return $this->blocked(); }
    public function query(FiscalDocument $document): array { return $this->blocked(); }
    public function cancel(FiscalDocument $document, string $idempotencyKey): array { return $this->blocked(); }
    public function downloadArtifacts(FiscalDocument $document): array { return $this->blocked(); }
    private function blocked(): never { throw new LogicException('Operações fiscais estão desabilitadas.'); }
}
