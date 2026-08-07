<?php

namespace App\Modules\Finance\Data;

final readonly class PaymentChargeResult
{
    public function __construct(
        public string $providerChargeId,
        public string $status,
        public ?string $checkoutUrl = null,
        public ?string $pixCopyPaste = null,
    ) {
    }
}
