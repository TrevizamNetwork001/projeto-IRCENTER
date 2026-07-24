<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'routing_incident_id',
    'user_id',
    'kind',
    'old_status',
    'new_status',
    'message',
])]
class RoutingIncidentUpdate extends Model
{
    public function incident(): BelongsTo
    {
        return $this->belongsTo(
            RoutingIncident::class,
            'routing_incident_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
