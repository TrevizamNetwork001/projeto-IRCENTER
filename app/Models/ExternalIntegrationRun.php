<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'external_integration_id',
    'requested_by_user_id',
    'operation',
    'status',
    'http_status',
    'duration_ms',
    'resolved_ip',
    'response_content_type',
    'error_message',
    'started_at',
    'finished_at',
])]
class ExternalIntegrationRun extends Model
{
    public function integration(): BelongsTo
    {
        return $this->belongsTo(ExternalIntegration::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
