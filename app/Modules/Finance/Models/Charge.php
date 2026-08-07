<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class Charge extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CREATED = 'created';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAILED = 'failed';

    public const METHOD_BOLETO = 'boleto';
    public const METHOD_PIX = 'pix';
    public const METHOD_BOLETO_PIX = 'boleto_pix';

    protected $connection = 'finance_fiscal';

    protected $table = 'charges';

    protected $fillable = [
        'invoice_id',
        'provider',
        'method',
        'status',
        'idempotency_key',
        'provider_charge_id',
        'amount',
        'currency',
        'due_on',
        'provider_checkout_url',
        'provider_pix_copy_paste',
        'provider_created_at',
        'last_synced_at',
        'last_provider_event_id',
        'last_provider_event_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'due_on' => 'date',
            'provider_created_at' =>
                'immutable_datetime',
            'last_synced_at' =>
                'immutable_datetime',
            'last_provider_event_at' =>
                'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $charge): void {
            if (! $charge->public_id) {
                $charge->public_id =
                    (string) Str::ulid();
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_id'
        );
    }
}
