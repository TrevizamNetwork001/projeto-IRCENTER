<?php

namespace App\Models;

use Database\Factories\IrrWorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'irr_workflow_id',
    'step_number',
    'step_key',
    'title',
    'instructions',
    'status',
    'email_to',
    'email_subject',
    'email_body',
    'rpsl_content',
    'prepared_at',
    'sent_at',
    'confirmed_at',
    'completed_at',
    'operator_notes',
])]
class IrrWorkflowStep extends Model
{
    /** @use HasFactory<IrrWorkflowStepFactory> */
    use HasFactory;

    public const STATUS_LOCKED = 'locked';

    public const STATUS_READY = 'ready';

    public const STATUS_SENT = 'sent';

    public const STATUS_WAITING_CONFIRMATION = 'waiting_confirmation';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ERROR = 'error';

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(IrrWorkflow::class, 'irr_workflow_id');
    }

    public function isAvailable(): bool
    {
        return $this->status !== self::STATUS_LOCKED;
    }

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'prepared_at' => 'datetime',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
