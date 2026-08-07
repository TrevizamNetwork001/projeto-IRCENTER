<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClientContact extends Model
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_FINANCIAL = 'financial';
    public const TYPE_FISCAL = 'fiscal';
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_ADMIN = 'admin';

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'email',
        'phone',
        'is_primary',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'is_primary' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
