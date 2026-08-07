<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceItem extends Model
{
    protected $connection = 'finance_fiscal';

    protected $table = 'invoice_items';

    protected $fillable = [
        'billing_contract_item_id',
        'service_code',
        'description',
        'quantity',
        'unit_amount',
        'line_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'billing_contract_item_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_id'
        );
    }
}
