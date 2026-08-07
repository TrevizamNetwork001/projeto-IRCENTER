<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'Eventos de auditoria são imutáveis.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Eventos de auditoria são imutáveis.'
            );
        });
    }

    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
