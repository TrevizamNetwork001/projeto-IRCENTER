<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMfaCredential extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'secret',
        'pending_secret',
        'recovery_codes',
        'last_used_step',
    ];

    protected function casts(): array
    {
        return [
            'recovery_codes' => 'array',
            'confirmed_at' => 'datetime',
            'last_used_step' => 'integer',
        ];
    }
}
