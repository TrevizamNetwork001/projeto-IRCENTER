<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'avatar_key',
    'avatar_mode',
    'avatar_photo_path',
    'email',
    'password',
    'role',
    'client_id',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isRestrictedToClient(): bool
    {
        return $this->client_id !== null;
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

    public const AVATAR_MODE_INITIALS = 'initials';

    public const AVATAR_MODE_AVATAR = 'avatar';

    public const AVATAR_MODE_PHOTO = 'photo';

    public function hasPhotoAvatar(): bool
    {
        return $this->avatar_mode === self::AVATAR_MODE_PHOTO
            && $this->avatar_photo_path !== null;
    }

    public function hasThemedAvatar(): bool
    {
        return $this->avatar_mode === self::AVATAR_MODE_AVATAR
            && $this->avatar_key !== null;
    }

    public function photoUrl(): ?string
    {
        if (! $this->hasPhotoAvatar()) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_photo_path);
    }

    public function avatarSymbol(): ?string
    {
        if (! $this->hasThemedAvatar()) {
            return null;
        }

        return self::avatars()[$this->avatar_key]['symbol'] ?? null;
    }

    public function avatarLabel(): ?string
    {
        if (! $this->hasThemedAvatar()) {
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
