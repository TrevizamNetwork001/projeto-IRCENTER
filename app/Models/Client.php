<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_code',
    'contract_number',
    'legal_name',
    'trade_name',
    'document',
    'email',
    'phone',
    'website',
    'postal_code',
    'street',
    'address_number',
    'address_complement',
    'district',
    'city',
    'state',
    'country',
    'notes',
    'active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    public function autonomousSystems(): HasMany
    {
        return $this->hasMany(AutonomousSystem::class);
    }

    public function routingIncidents(): HasMany
    {
        return $this->hasMany(RoutingIncident::class);
    }

    public function prefixes(): HasMany
    {
        return $this->hasMany(Prefix::class);
    }

    public function irrObjects(): HasMany
    {
        return $this->hasMany(IrrObject::class);
    }

    public function rpkiRoas(): HasMany
    {
        return $this->hasMany(RpkiRoa::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    public function formattedDocument(): string
    {
        if (! $this->document) {
            return '—';
        }

        if (strlen($this->document) === 11) {
            return preg_replace(
                '/^(\d{3})(\d{3})(\d{3})(\d{2})$/',
                '$1.$2.$3-$4',
                $this->document
            );
        }

        if (strlen($this->document) === 14) {
            return preg_replace(
                '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/',
                '$1.$2.$3/$4-$5',
                $this->document
            );
        }

        return $this->document;
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

    public function formattedPostalCode(): string
    {
        if (! $this->postal_code) {
            return '—';
        }

        return preg_replace(
            '/^(\d{5})(\d{3})$/',
            '$1-$2',
            $this->postal_code
        );
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
