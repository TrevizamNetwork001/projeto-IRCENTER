<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'submittable_type',
    'submittable_id',
    'operation',
    'source',
    'request_payload',
    'response_payload',
    'successful',
])]
class IrrSubmission extends Model
{
    public const UPDATED_AT = null;

    public const OPERATION_CREATE = 'create';

    public const OPERATION_MODIFY = 'modify';

    public const OPERATION_DELETE = 'delete';

    protected $attributes = [
        'source' => 'TC',
    ];

    public function submittable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'successful' => 'boolean',
        ];
    }
}
