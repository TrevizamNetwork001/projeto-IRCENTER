<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Data\PaymentChargeResult;

interface CorrelatablePaymentProvider
{
    public function findChargeByCorrelation(
        string $correlationId,
        string $beginDate,
        string $endDate,
    ): ?PaymentChargeResult;
}
