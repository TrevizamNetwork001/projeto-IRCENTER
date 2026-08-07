<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PaymentWebhookReceipt extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $connection = 'finance_fiscal';

    protected $table =
        'payment_webhook_receipts';

    protected $fillable = [
        'provider',
        'token_hash',
        'token_encrypted',
        'status',
        'attempt_count',
        'received_at',
        'processing_started_at',
        'processed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'received_at' => 'immutable_datetime',
            'processing_started_at' =>
                'immutable_datetime',
            'processed_at' =>
                'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (self $receipt): void {
                if (! $receipt->public_id) {
                    $receipt->public_id =
                        (string) Str::ulid();
                }
            }
        );
    }
}
