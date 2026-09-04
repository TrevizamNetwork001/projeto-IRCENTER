<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_id',
    'asn',
    'mntner',
    'source',
    'password',
    'admin_c',
    'tech_c',
    'descr',
    'notify_email',
])]
class IrrMaintainer extends Model
{
    use BelongsToClient;

    protected $hidden = ['password'];

    protected $attributes = [
        'source' => 'TC',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(IrrRoute::class);
    }

    public function asSets(): HasMany
    {
        return $this->hasMany(IrrAsSet::class);
    }

    public function formattedAsn(): string
    {
        return 'AS'.$this->asn;
    }

    protected function casts(): array
    {
        return [
            'asn' => 'integer',
            'password' => 'encrypted',
        ];
    }
}
