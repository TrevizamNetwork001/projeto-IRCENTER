<?php

namespace App\Modules\Finance\Data;

final readonly class PaymentChargeRequest
{
    public function __construct(
        public string $invoicePublicId,
        public string $idempotencyKey,
        public string $method,
        public string $amount,
        public string $currency,
        public string $dueOn,
        public string $payerName,
        public ?string $payerDocument,
        public ?string $payerEmail,
    ) {
    }
}
