<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'unique_key',
    'type',
    'priority',
    'title',
    'message',
    'action_url',
    'read_at',
    'resolved_at',
])]
class Notification extends Model
{
    public const PRIORITY_INFO = 'info';
    public const PRIORITY_WARNING = 'warning';
    public const PRIORITY_CRITICAL = 'critical';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            self::PRIORITY_CRITICAL => 'Crítica',
            self::PRIORITY_WARNING => 'Atenção',
            default => 'Informativa',
        };
    }

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
