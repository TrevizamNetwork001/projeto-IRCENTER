<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FiscalArtifact extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['fiscal_document_id', 'type', 'storage_disk', 'storage_path', 'original_filename', 'mime_type', 'sha256', 'size'];
    protected function casts(): array { return ['size' => 'integer']; }
    public function document(): BelongsTo { return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id'); }
}
