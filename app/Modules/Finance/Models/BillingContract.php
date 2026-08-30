<?php

namespace App\Modules\Finance\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class BillingContract extends Model
{
    use BelongsToClient;

    public function clientForeignKeyColumn(): string
    {
        return 'core_client_id';
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ENDED = 'ended';

    public const FREQUENCY_MONTHLY = 'monthly';

    protected $connection = 'finance_fiscal';

    protected $table = 'billing_contracts';

    protected $fillable = [
        'core_client_id',
        'client_code_snapshot',
        'client_legal_name_snapshot',
        'client_trade_name_snapshot',
        'client_document_snapshot',
        'frequency',
        'generation_day',
        'due_day',
        'currency',
        'status',
        'billing_email_override',
        'auto_charge',
        'send_email',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'core_client_id' => 'integer',
            'generation_day' => 'integer',
            'due_day' => 'integer',
            'auto_charge' => 'boolean',
            'send_email' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            if (! $contract->public_id) {
                $contract->public_id = (string) Str::ulid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            BillingContractItem::class,
            'billing_contract_id'
        )->orderBy('sort_order');
    }
}
