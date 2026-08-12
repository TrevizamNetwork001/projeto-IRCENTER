<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class FiscalCustomerProfile extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['core_client_id', 'document', 'legal_name', 'municipal_registration', 'state_registration', 'fiscal_email', 'phone', 'postal_code', 'street', 'address_number', 'address_complement', 'district', 'municipality', 'municipality_code', 'state', 'country_code', 'country_numeric_code'];

    protected function casts(): array
    {
        return ['core_client_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $profile) => $profile->public_id ??= (string) Str::ulid());
    }

    public function snapshot(): array
    {
        return $this->only(['public_id', 'core_client_id', 'document', 'legal_name', 'municipal_registration', 'state_registration', 'fiscal_email', 'phone', 'postal_code', 'street', 'address_number', 'address_complement', 'district', 'municipality', 'municipality_code', 'state', 'country_code', 'country_numeric_code']);
    }
}
