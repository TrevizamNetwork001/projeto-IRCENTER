<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'irr_maintainer_id',
    'prefix',
    'version',
    'origin_asn',
    'source',
    'descr',
    'member_of',
    'remarks',
    'notify',
    'status',
])]
class IrrRoute extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $attributes = [
        'source' => 'TC',
        'status' => self::STATUS_PENDING,
    ];

    public function maintainer(): BelongsTo
    {
        return $this->belongsTo(IrrMaintainer::class, 'irr_maintainer_id');
    }

    public function submissions(): MorphMany
    {
        return $this->morphMany(IrrSubmission::class, 'submittable');
    }

    public function attributeName(): string
    {
        return $this->version === 6 ? 'route6' : 'route';
    }

    public function markPublished(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'last_published_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'last_error' => mb_substr($error, 0, 2000),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'origin_asn' => 'integer',
            'member_of' => 'array',
            'notify' => 'array',
            'last_published_at' => 'datetime',
        ];
    }
}
