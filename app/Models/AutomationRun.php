<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'automation',
    'trigger',
    'status',
    'started_at',
    'finished_at',
    'duration_ms',
    'processed_items',
    'result_items',
    'metadata',
    'error_message',
])]
class AutomationRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
