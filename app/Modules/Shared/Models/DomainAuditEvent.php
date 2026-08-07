<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;

final class DomainAuditEvent extends Model
{
    public $timestamps = false;

    protected $connection = 'finance_fiscal';

    protected $table = 'domain_audit_events';

    protected $fillable = [
        'module',
        'action',
        'actor_user_id',
        'entity_type',
        'entity_id',
        'correlation_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
