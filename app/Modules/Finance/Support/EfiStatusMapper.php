<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\Charge;

final class EfiStatusMapper
{
    public static function toLocal(string $status): string
    {
        return match (strtolower(trim($status))) {
            'new',
            'waiting',
            'identified',
            'approved',
            'unpaid' => Charge::STATUS_OPEN,

            'paid',
            'settled' => Charge::STATUS_PAID,

            'expired' => Charge::STATUS_OVERDUE,
            'canceled' => Charge::STATUS_CANCELED,

            'refunded',
            'contested' => Charge::STATUS_FAILED,

            default => Charge::STATUS_FAILED,
        };
    }
}
