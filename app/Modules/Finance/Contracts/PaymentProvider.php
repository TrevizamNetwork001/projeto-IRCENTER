<?php

namespace App\Modules\Finance\Contracts;

interface PaymentProvider
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    public function isLive(): bool;
}
