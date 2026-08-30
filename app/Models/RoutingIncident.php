<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'client_id',
    'autonomous_system_id',
    'prefix_id',
    'reported_by_user_id',
    'assigned_to_user_id',
    'title',
    'type',
    'severity',
    'status',
    'source',
    'summary',
    'impact',
    'evidence',
    'mitigation',
    'root_cause',
    'external_reference',
    'detected_at',
    'acknowledged_at',
    'resolved_at',
    'closed_at',
])]
class RoutingIncident extends Model
{
    use BelongsToClient;

    public const TYPE_ROUTE_LEAK = 'route_leak';
    public const TYPE_PREFIX_HIJACK = 'prefix_hijack';
    public const TYPE_UNEXPECTED_ANNOUNCEMENT = 'unexpected_announcement';
    public const TYPE_RPKI_INVALID = 'rpki_invalid';
    public const TYPE_IRR_CHANGE = 'irr_change';
    public const TYPE_PEER_OUTAGE = 'peer_outage';
    public const TYPE_DDOS = 'ddos';
    public const TYPE_SECURITY = 'security';
    public const TYPE_OTHER = 'other';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_OPEN = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_MITIGATING = 'mitigating';
    public const STATUS_MONITORING = 'monitoring';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public static function types(): array
    {
        return [
            self::TYPE_ROUTE_LEAK,
            self::TYPE_PREFIX_HIJACK,
            self::TYPE_UNEXPECTED_ANNOUNCEMENT,
            self::TYPE_RPKI_INVALID,
            self::TYPE_IRR_CHANGE,
            self::TYPE_PEER_OUTAGE,
            self::TYPE_DDOS,
            self::TYPE_SECURITY,
            self::TYPE_OTHER,
        ];
    }

    public static function severities(): array
    {
        return [
            self::SEVERITY_LOW,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_INVESTIGATING,
            self::STATUS_MITIGATING,
            self::STATUS_MONITORING,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function autonomousSystem(): BelongsTo
    {
        return $this->belongsTo(AutonomousSystem::class);
    }

    public function prefix(): BelongsTo
    {
        return $this->belongsTo(Prefix::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by_user_id'
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to_user_id'
        );
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RoutingIncidentUpdate::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_ROUTE_LEAK => 'Vazamento de rota',
            self::TYPE_PREFIX_HIJACK => 'Sequestro de prefixo',
            self::TYPE_UNEXPECTED_ANNOUNCEMENT => 'Anúncio inesperado',
            self::TYPE_RPKI_INVALID => 'Invalidação RPKI',
            self::TYPE_IRR_CHANGE => 'Alteração suspeita em IRR',
            self::TYPE_PEER_OUTAGE => 'Indisponibilidade de peer ou trânsito',
            self::TYPE_DDOS => 'DDoS ou abuso',
            self::TYPE_SECURITY => 'Incidente de segurança',
            default => 'Outro',
        };
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'Crítica',
            self::SEVERITY_HIGH => 'Alta',
            self::SEVERITY_MEDIUM => 'Média',
            default => 'Baixa',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_INVESTIGATING => 'Em investigação',
            self::STATUS_MITIGATING => 'Em mitigação',
            self::STATUS_MONITORING => 'Em monitoramento',
            self::STATUS_RESOLVED => 'Resolvido',
            self::STATUS_CLOSED => 'Encerrado',
            default => 'Aberto',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array(
            $this->status,
            [
                self::STATUS_RESOLVED,
                self::STATUS_CLOSED,
            ],
            true
        );
    }

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
