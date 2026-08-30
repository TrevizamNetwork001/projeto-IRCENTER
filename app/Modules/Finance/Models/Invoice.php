<?php

namespace App\Modules\Finance\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Invoice extends Model
{
    use BelongsToClient;

    public function clientForeignKeyColumn(): string
    {
        return 'core_client_id';
    }

    public const SOURCE_RECURRING = 'recurring';
    public const SOURCE_ONE_OFF = 'one_off';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';

    protected $connection = 'finance_fiscal';

    protected $table = 'invoices';

    protected $fillable = [
        'billing_contract_id',
        'core_client_id',
        'client_code_snapshot',
        'client_legal_name_snapshot',
        'client_trade_name_snapshot',
        'client_document_snapshot',
        'billing_email_snapshot',
        'client_phone_snapshot',
        'client_postal_code_snapshot',
        'client_street_snapshot',
        'client_address_number_snapshot',
        'client_address_complement_snapshot',
        'client_district_snapshot',
        'client_city_snapshot',
        'client_state_snapshot',
        'client_country_snapshot',
        'source',
        'competence_month',
        'generation_key',
        'issued_on',
        'due_on',
        'currency',
        'status',
        'subtotal',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'billing_contract_id' => 'integer',
            'core_client_id' => 'integer',
            'competence_month' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (! $invoice->public_id) {
                $invoice->public_id =
                    (string) Str::ulid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            InvoiceItem::class,
            'invoice_id'
        )->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class, 'invoice_id');
    }
}
