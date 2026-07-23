<?php

namespace App\Models;

use Database\Factories\RpkiRoaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_id',
    'autonomous_system_id',
    'prefix_id',
    'prefix',
    'ip_version',
    'asn',
    'max_length',
    'source',
    'tal',
    'status',
    'payload_hash',
    'not_before',
    'not_after',
    'last_seen_at',
    'metadata',
    'active',
])]
class RpkiRoa extends Model
{
    /** @use HasFactory<RpkiRoaFactory> */
    use HasFactory;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function autonomousSystem(): BelongsTo
    {
        return $this->belongsTo(AutonomousSystem::class);
    }

    public function prefixRecord(): BelongsTo
    {
        return $this->belongsTo(Prefix::class, 'prefix_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(RpkiValidation::class);
    }

    public function formattedAsn(): string
    {
        return 'AS'.$this->asn;
    }

    protected function casts(): array
    {
        return [
            'ip_version' => 'integer',
            'asn' => 'integer',
            'max_length' => 'integer',
            'not_before' => 'datetime',
            'not_after' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
            'active' => 'boolean',
        ];
    }
}
