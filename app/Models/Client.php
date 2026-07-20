<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legal_name',
    'trade_name',
    'document',
    'email',
    'phone',
    'website',
    'city',
    'state',
    'country',
    'notes',
    'active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    public function autonomousSystems(): HasMany
    {
        return $this->hasMany(AutonomousSystem::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
