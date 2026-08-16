<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'identifier',
    'token_hash',
    'token_prefix',
    'is_active',
    'expires_at',
    'last_used_at',
    'last_used_ip',
    'revoked_at',
    'scopes',
])]
class ApiClient extends Model
{
    protected $attributes = [
        'scopes' => '[]',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'scopes' => 'array',
        ];
    }
}
