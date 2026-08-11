<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClientContact extends Model
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_FINANCIAL = 'financial';
    public const TYPE_FISCAL = 'fiscal';
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_ADMIN = 'admin';

    public const TYPES = [
        self::TYPE_GENERAL,
        self::TYPE_FINANCIAL,
        self::TYPE_FISCAL,
        self::TYPE_TECHNICAL,
        self::TYPE_ADMIN,
    ];

    public const TYPE_LABELS = [
        self::TYPE_GENERAL => 'Geral',
        self::TYPE_FINANCIAL => 'Financeiro',
        self::TYPE_FISCAL => 'Fiscal',
        self::TYPE_TECHNICAL => 'Técnico',
        self::TYPE_ADMIN => 'Administrativo',
    ];

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'email',
        'phone',
        'is_primary',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'is_primary' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function formattedPhone(): string
    {
        if (! $this->phone) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $this->phone);

        if (str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return preg_replace(
                '/^(\d{2})(\d{5})(\d{4})$/',
                '($1) $2-$3',
                $digits
            );
        }

        if (strlen($digits) === 10) {
            return preg_replace(
                '/^(\d{2})(\d{4})(\d{4})$/',
                '($1) $2-$3',
                $digits
            );
        }

        return $this->phone;
    }
}
