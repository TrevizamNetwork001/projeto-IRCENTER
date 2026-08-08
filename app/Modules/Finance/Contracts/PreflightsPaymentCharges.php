<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Data\PaymentChargeRequest;

interface PreflightsPaymentCharges
{
    public function preflightCharge(PaymentChargeRequest $request): void;
}
