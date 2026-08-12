<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class FiscalIssuerProfile extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['legal_name', 'trade_name', 'document', 'municipal_registration', 'municipality_code', 'municipality', 'state', 'postal_code', 'street', 'address_number', 'address_complement', 'district', 'phone', 'email', 'tax_settings', 'active'];

    protected function casts(): array
    {
        return ['tax_settings' => 'array', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $profile) => $profile->public_id ??= (string) Str::ulid());
    }

    public function snapshot(): array
    {
        return $this->only(['public_id', 'legal_name', 'trade_name', 'document', 'municipal_registration', 'municipality_code', 'municipality', 'state', 'postal_code', 'street', 'address_number', 'address_complement', 'district', 'phone', 'email', 'tax_settings']);
    }
}
