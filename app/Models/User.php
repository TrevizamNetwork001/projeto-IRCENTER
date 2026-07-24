<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'avatar_key',
    'email',
    'password',
    'role',
    'active',
    'must_change_password',
    'password_changed_at',
    'last_login_at',
    'last_login_ip',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_VIEWER = 'viewer';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canOperate(): bool
    {
        return in_array(
            $this->role,
            [
                self::ROLE_ADMIN,
                self::ROLE_OPERATOR,
            ],
            true
        );
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_OPERATOR,
            self::ROLE_VIEWER,
        ];
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_OPERATOR => 'Operador',
            default => 'Somente leitura',
        };
    }

    /**
     * @return array<string, array{label: string, symbol: string}>
     */
    public static function avatars(): array
    {
        return [
            'astronaut' => [
                'label' => 'Astronauta',
                'symbol' => '🧑‍🚀',
            ],
            'robot' => [
                'label' => 'Robô',
                'symbol' => '🤖',
            ],
            'wolf' => [
                'label' => 'Lobo',
                'symbol' => '🐺',
            ],
            'eagle' => [
                'label' => 'Águia',
                'symbol' => '🦅',
            ],
            'cat' => [
                'label' => 'Gato',
                'symbol' => '🐱',
            ],
            'fox' => [
                'label' => 'Raposa',
                'symbol' => '🦊',
            ],
            'owl' => [
                'label' => 'Coruja',
                'symbol' => '🦉',
            ],
            'technician' => [
                'label' => 'Técnico',
                'symbol' => '🧑‍💻',
            ],
            'operator' => [
                'label' => 'Operador',
                'symbol' => '🎧',
            ],
            'shield' => [
                'label' => 'Escudo',
                'symbol' => '🛡️',
            ],
            'network' => [
                'label' => 'Rede',
                'symbol' => '🌐',
            ],
            'server' => [
                'label' => 'Servidor',
                'symbol' => '🖥️',
            ],
        ];
    }

    public function avatarSymbol(): ?string
    {
        if ($this->avatar_key === null) {
            return null;
        }

        return self::avatars()[$this->avatar_key]['symbol'] ?? null;
    }

    public function avatarLabel(): ?string
    {
        if ($this->avatar_key === null) {
            return null;
        }

        return self::avatars()[$this->avatar_key]['label'] ?? null;
    }

    public function initials(): string
    {
        $parts = preg_split(
            '/\s+/u',
            trim($this->name),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (! $parts) {
            return '?';
        }

        $initials = mb_substr($parts[0], 0, 1);

        if (count($parts) > 1) {
            $initials .= mb_substr($parts[array_key_last($parts)], 0, 1);
        }

        return mb_strtoupper($initials);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
