<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentProviderEvent extends Model
{
    protected $connection = 'finance_fiscal';

    protected $table =
        'payment_provider_events';

    protected $fillable = [
        'webhook_receipt_id',
        'charge_id',
        'provider',
        'notification_token_hash',
        'provider_event_id',
        'provider_charge_id',
        'event_type',
        'status_current',
        'status_previous',
        'value_cents',
        'received_by_bank_at',
        'provider_created_at_raw',
        'payload_json',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'webhook_receipt_id' => 'integer',
            'charge_id' => 'integer',
            'value_cents' => 'integer',
            'received_by_bank_at' => 'date',
            'payload_json' => 'array',
            'processed_at' =>
                'immutable_datetime',
        ];
    }
}
