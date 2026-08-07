<?php

namespace App\Modules\Fiscal\Contracts;

interface NfseProvider
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    public function isLive(): bool;
}
