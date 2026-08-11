<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BillingItem extends Model
{
    protected $connection = 'finance_fiscal';
    protected $table = 'billing_items';
    protected $fillable = ['name', 'description', 'default_amount', 'active'];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->public_id ??= (string) Str::ulid();
        });
    }
}
