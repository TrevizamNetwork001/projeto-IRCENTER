<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;

final class FiscalArtifact extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['fiscal_document_id', 'type', 'storage_disk', 'storage_path', 'mime_type', 'sha256', 'size'];
    protected function casts(): array { return ['size' => 'integer']; }
}
