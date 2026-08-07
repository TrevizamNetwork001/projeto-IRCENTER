<?php

namespace App\Modules\Fiscal\Infrastructure;

use App\Modules\Fiscal\Contracts\NfseProvider;

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
}
