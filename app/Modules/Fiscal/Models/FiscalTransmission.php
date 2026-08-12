<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;

final class FiscalTransmission extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['fiscal_document_id', 'provider', 'operation', 'attempt_number', 'started_at', 'finished_at', 'result', 'response_code', 'sanitized_message', 'correlation_id'];
    protected function casts(): array { return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime']; }
}
