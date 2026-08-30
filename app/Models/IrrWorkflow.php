<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Database\Factories\IrrWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_id',
    'autonomous_system_id',
    'name',
    'profile_key',
    'irr_source',
    'destination_email',
    'maintainer',
    'as_set',
    'route_set',
    'contact_name',
    'contact_handle',
    'admin_contact_handle',
    'tech_contact_handle',
    'contact_email',
    'noc_email',
    'abuse_email',
    'peering_email',
    'peeringdb_url',
    'looking_glass_url',
    'website_url',
    'custom_remarks',
    'contact_phone',
    'contact_address',
    'status',
    'current_step',
    'started_at',
    'completed_at',
    'notes',
])]
class IrrWorkflow extends Model
{
    /** @use HasFactory<IrrWorkflowFactory> */
    use HasFactory, BelongsToClient;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function autonomousSystem(): BelongsTo
    {
        return $this->belongsTo(AutonomousSystem::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(IrrWorkflowStep::class)
            ->orderBy('step_number');
    }

    public function prefixes(): HasMany
    {
        return $this->hasMany(IrrWorkflowPrefix::class);
    }

    public function asSets(): HasMany
    {
        return $this->hasMany(IrrWorkflowAsSet::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(IrrWorkflowRelationship::class);
    }

    public function currentStepRecord(): ?IrrWorkflowStep
    {
        return $this->steps()
            ->where('step_number', $this->current_step)
            ->first();
    }

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
