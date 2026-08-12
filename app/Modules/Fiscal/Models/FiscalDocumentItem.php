<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FiscalDocumentItem extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['fiscal_document_id', 'fiscal_service_profile_id', 'billing_item_id', 'description', 'quantity', 'unit_amount', 'total_amount', 'classification_snapshot', 'tax_snapshot', 'sort_order'];
    protected function casts(): array { return ['quantity' => 'decimal:4', 'unit_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'classification_snapshot' => 'array', 'tax_snapshot' => 'array']; }
    public function document(): BelongsTo { return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id'); }
    public function service(): BelongsTo { return $this->belongsTo(FiscalServiceProfile::class, 'fiscal_service_profile_id'); }
}
