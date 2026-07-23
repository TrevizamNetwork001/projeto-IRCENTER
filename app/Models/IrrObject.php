<?php

namespace App\Models;

use Database\Factories\IrrObjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id',
    'autonomous_system_id',
    'prefix_id',
    'object_type',
    'object_key',
    'source',
    'maintainer',
    'status',
    'description',
    'raw_text',
    'attributes',
    'last_synced_at',
    'active',
])]
class IrrObject extends Model
{
    /** @use HasFactory<IrrObjectFactory> */
    use HasFactory;

    public const TYPES = [
        'route',
        'route6',
        'aut-num',
        'as-set',
        'mntner',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function autonomousSystem(): BelongsTo
    {
        return $this->belongsTo(AutonomousSystem::class);
    }

    public function prefix(): BelongsTo
    {
        return $this->belongsTo(Prefix::class);
    }

    public function displayType(): string
    {
        return match ($this->object_type) {
            'route' => 'Route IPv4',
            'route6' => 'Route IPv6',
            'aut-num' => 'Aut-num',
            'as-set' => 'AS-set',
            'mntner' => 'Maintainer',
            default => $this->object_type,
        };
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'last_synced_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
