<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingContractItem extends Model
{
    protected $connection = 'finance_fiscal';

    protected $table = 'billing_contract_items';

    protected $fillable = [
        'billing_item_id',
        'service_code',
        'description',
        'quantity',
        'unit_amount',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'billing_item_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_amount' => 'decimal:2',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(
            BillingContract::class,
            'billing_contract_id'
        );
    }
}
