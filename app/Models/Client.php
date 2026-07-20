<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }
}
