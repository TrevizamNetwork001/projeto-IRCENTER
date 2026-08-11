<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class Payment extends Model
{
    protected $connection = 'finance_fiscal';

    protected $fillable = [
        'charge_id',
        'invoice_id',
        'provider',
        'provider_payment_id',
        'amount',
        'currency',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'charge_id' => 'integer',
            'invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (! $payment->public_id) {
                $payment->public_id = (string) Str::ulid();
            }
        });
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
