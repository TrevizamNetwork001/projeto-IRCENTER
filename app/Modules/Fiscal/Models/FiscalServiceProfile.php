<?php

namespace App\Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class FiscalServiceProfile extends Model
{
    protected $connection = 'finance_fiscal';
    protected $fillable = ['billing_item_id', 'fiscal_description', 'national_service_code', 'municipal_service_code', 'nbs_code', 'municipality_code', 'municipality', 'iss_rate', 'tax_settings', 'withholding_settings', 'active'];

    protected function casts(): array
    {
        return ['billing_item_id' => 'integer', 'iss_rate' => 'decimal:4', 'tax_settings' => 'array', 'withholding_settings' => 'array', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $profile) => $profile->public_id ??= (string) Str::ulid());
    }

    public function snapshot(): array
    {
        return $this->only(['public_id', 'billing_item_id', 'fiscal_description', 'national_service_code', 'municipal_service_code', 'nbs_code', 'municipality_code', 'municipality', 'iss_rate', 'tax_settings', 'withholding_settings']);
    }
}
