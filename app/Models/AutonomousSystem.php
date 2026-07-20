<?php

namespace App\Models;

use Database\Factories\AutonomousSystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id',
    'asn',
    'name',
    'description',
    'rir',
    'country',
    'website',
    'noc_contact',
    'noc_email',
    'noc_phone',
    'notes',
    'active',
])]
class AutonomousSystem extends Model
{
    /** @use HasFactory<AutonomousSystemFactory> */
    use HasFactory;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function formattedAsn(): string
    {
        return 'AS'.$this->asn;
    }

    protected function casts(): array
    {
        return [
            'asn' => 'integer',
            'active' => 'boolean',
        ];
    }
}
