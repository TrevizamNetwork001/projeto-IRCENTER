<?php

namespace App\Models;

use Database\Factories\RpkiValidationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'prefix_id',
    'rpki_roa_id',
    'status',
    'reason',
    'validated_prefix',
    'ip_version',
    'validated_asn',
    'matched_max_length',
    'matching_roas_count',
    'source',
    'details',
    'metadata',
    'checked_at',
])]
class RpkiValidation extends Model
{
    /** @use HasFactory<RpkiValidationFactory> */
    use HasFactory;

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_ERROR = 'error';

    public function prefix(): BelongsTo
    {
        return $this->belongsTo(Prefix::class);
    }

    public function roa(): BelongsTo
    {
        return $this->belongsTo(RpkiRoa::class, 'rpki_roa_id');
    }

    public function displayStatus(): string
    {
        return match ($this->status) {
            self::STATUS_VALID => 'Válido',
            self::STATUS_INVALID => 'Inválido',
            self::STATUS_NOT_FOUND => 'Não encontrado',
            self::STATUS_ERROR => 'Erro',
            default => $this->status,
        };
    }

    protected function casts(): array
    {
        return [
            'ip_version' => 'integer',
            'validated_asn' => 'integer',
            'matched_max_length' => 'integer',
            'matching_roas_count' => 'integer',
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
