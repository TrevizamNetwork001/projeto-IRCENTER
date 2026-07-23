<?php

namespace App\Models;

use Database\Factories\PrefixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id',
    'autonomous_system_id',
    'prefix',
    'ip_version',
    'description',
    'rir',
    'country',
    'allocation_status',
    'purpose',
    'notes',
    'active',
])]
class Prefix extends Model
{
    /** @use HasFactory<PrefixFactory> */
    use HasFactory;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function autonomousSystem(): BelongsTo
    {
        return $this->belongsTo(AutonomousSystem::class);
    }

    public function irrObjects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IrrObject::class);
    }

    public function rpkiRoas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RpkiRoa::class);
    }

    public function rpkiValidations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RpkiValidation::class);
    }

    public function latestRpkiValidation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RpkiValidation::class)->latestOfMany('checked_at');
    }

    public function isIpv4(): bool
    {
        return $this->ip_version === 4;
    }

    public function isIpv6(): bool
    {
        return $this->ip_version === 6;
    }

    protected function casts(): array
    {
        return [
            'ip_version' => 'integer',
            'active' => 'boolean',
        ];
    }
}
