<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'irr_workflow_id',
    'prefix_id',
    'ip_version',
    'prefix',
    'route_set_mode',
    'maximum_length',
    'generate_route_object',
    'active',
])]
class IrrWorkflowPrefix extends Model
{
    public const MODE_EXACT = 'exact';

    public const MODE_MORE_SPECIFICS = 'more_specifics';

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            IrrWorkflow::class,
            'irr_workflow_id'
        );
    }

    public function sourcePrefix(): BelongsTo
    {
        return $this->belongsTo(Prefix::class, 'prefix_id');
    }

    public function routeSetMember(): string
    {
        $baseLength = $this->prefixLength();

        if (
            $this->route_set_mode === self::MODE_MORE_SPECIFICS
            && $this->maximum_length !== null
            && $this->maximum_length > $baseLength
        ) {
            return sprintf(
                '%s^%d-%d',
                $this->prefix,
                $baseLength,
                $this->maximum_length
            );
        }

        return $this->prefix;
    }

    public function prefixLength(): int
    {
        return (int) str($this->prefix)->afterLast('/')->toString();
    }

    protected function casts(): array
    {
        return [
            'ip_version' => 'integer',
            'maximum_length' => 'integer',
            'generate_route_object' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
