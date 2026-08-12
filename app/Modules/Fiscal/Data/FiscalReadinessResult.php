<?php

namespace App\Modules\Fiscal\Data;

final readonly class FiscalReadinessResult
{
    public function __construct(public string $status, public array $issues) {}

    public function isBlocked(): bool
    {
        return $this->status === 'BLOCKED';
    }
}
