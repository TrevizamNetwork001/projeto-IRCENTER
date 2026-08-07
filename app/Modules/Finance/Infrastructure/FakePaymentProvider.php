<?php

namespace App\Modules\Finance\Infrastructure;

use App\Modules\Finance\Contracts\PaymentProvider;

final class FakePaymentProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'fake';
    }

    public function capabilities(): array
    {
        return [
            'boleto',
            'pix',
            'boleto_pix',
            'webhook',
            'cancel',
        ];
    }

    public function isLive(): bool
    {
        return false;
    }
}
