<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'irr_workflow_id',
    'name',
    'purpose',
    'description',
    'members',
    'active',
])]
class IrrWorkflowAsSet extends Model
{
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            IrrWorkflow::class,
            'irr_workflow_id'
        );
    }

    protected function casts(): array
    {
        return [
            'members' => 'array',
            'active' => 'boolean',
        ];
    }
}
