<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'type',
    'endpoint',
    'authentication_type',
    'username',
    'secret',
    'timeout_seconds',
    'active',
    'last_tested_at',
    'last_test_status',
    'last_http_status',
    'last_error',
    'created_by_user_id',
    'updated_by_user_id',
])]
class ExternalIntegration extends Model
{
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_IRR = 'irr';
    public const TYPE_RPKI = 'rpki';
    public const TYPE_MONITORING = 'monitoring';
    public const TYPE_OTHER = 'other';

    public const AUTH_NONE = 'none';
    public const AUTH_BEARER = 'bearer';
    public const AUTH_BASIC = 'basic';

    public const TEST_PENDING = 'pending';
    public const TEST_RUNNING = 'running';
    public const TEST_SUCCESS = 'success';
    public const TEST_FAILED = 'failed';
    public const TEST_BLOCKED = 'blocked';

    public static function types(): array
    {
        return [
            self::TYPE_WEBHOOK,
            self::TYPE_IRR,
            self::TYPE_RPKI,
            self::TYPE_MONITORING,
            self::TYPE_OTHER,
        ];
    }

    public static function authenticationTypes(): array
    {
        return [
            self::AUTH_NONE,
            self::AUTH_BEARER,
            self::AUTH_BASIC,
        ];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_WEBHOOK => 'Webhook',
            self::TYPE_IRR => 'IRR',
            self::TYPE_RPKI => 'RPKI',
            self::TYPE_MONITORING => 'Monitoramento',
            default => 'Outra',
        };
    }

    public function authenticationLabel(): string
    {
        return match ($this->authentication_type) {
            self::AUTH_BEARER => 'Bearer token',
            self::AUTH_BASIC => 'Usuário e senha',
            default => 'Sem autenticação',
        };
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ExternalIntegrationRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id'
        );
    }

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'timeout_seconds' => 'integer',
            'active' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_http_status' => 'integer',
        ];
    }
}
