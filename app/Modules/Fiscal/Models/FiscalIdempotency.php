<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;

final class FiscalIdempotency extends Model
{
    protected $connection = 'finance_fiscal';
    protected $table = 'fiscal_idempotencies';
    protected $fillable = ['fiscal_document_id', 'operation', 'idempotency_key', 'provider'];
}
