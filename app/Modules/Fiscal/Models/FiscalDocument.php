<?php

namespace App\Modules\Fiscal\Models;

use App\Models\Concerns\BelongsToClient;
use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Enums\FiscalEnvironment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class FiscalDocument extends Model
{
    use BelongsToClient;

    public function clientForeignKeyColumn(): string
    {
        return 'core_client_id';
    }

    protected $connection = 'finance_fiscal';
    protected $fillable = ['core_client_id', 'fiscal_issuer_profile_id', 'fiscal_customer_profile_id', 'billing_contract_id', 'invoice_id', 'charge_id', 'billing_item_id', 'environment', 'provider', 'status', 'competence_date', 'service_date', 'services_amount', 'discount_amount', 'deduction_amount', 'withholding_amount', 'net_amount', 'currency', 'summary', 'idempotency_key', 'external_reference', 'nfse_number', 'access_key', 'verification_code', 'authorized_at', 'cancelled_at', 'rejection_code', 'rejection_message', 'emission_origin', 'registered_by_user_id', 'manual_authorization_notes'];

    protected function casts(): array
    {
        return ['core_client_id' => 'integer', 'registered_by_user_id' => 'integer', 'environment' => FiscalEnvironment::class, 'status' => FiscalDocumentStatus::class, 'competence_date' => 'date', 'service_date' => 'date', 'services_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'deduction_amount' => 'decimal:2', 'withholding_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'issuer_snapshot' => 'array', 'customer_snapshot' => 'array', 'service_snapshot' => 'array', 'values_snapshot' => 'array', 'tax_snapshot' => 'array', 'prepared_at' => 'immutable_datetime', 'authorized_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $document) => $document->public_id ??= (string) Str::ulid());
        static::updating(function (self $document): void {
            if ($document->getRawOriginal('status') === FiscalDocumentStatus::Authorized->value) {
                $immutable = ['core_client_id', 'fiscal_issuer_profile_id', 'fiscal_customer_profile_id', 'competence_date', 'service_date', 'services_amount', 'discount_amount', 'deduction_amount', 'withholding_amount', 'net_amount', 'currency', 'nfse_number', 'access_key', 'verification_code', 'authorized_at', 'registered_by_user_id'];
                if ($document->isDirty($immutable)) {
                    throw new LogicException('Documento fiscal autorizado é imutável.');
                }
            }
            foreach (['issuer_snapshot', 'customer_snapshot', 'service_snapshot', 'values_snapshot', 'tax_snapshot', 'prepared_at'] as $field) {
                $returningToDraft = $document->status === FiscalDocumentStatus::Draft && $document->getAttribute($field) === null;
                if ($document->getOriginal('prepared_at') !== null && $document->isDirty($field) && ! $returningToDraft) {
                    throw new LogicException('Snapshots fiscais preparados são imutáveis.');
                }
            }
        });
    }

    public function transitionTo(FiscalDocumentStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException("Transição fiscal inválida: {$this->status->value} -> {$target->value}.");
        }
        $this->status = $target;
    }

    public function issuer(): BelongsTo { return $this->belongsTo(FiscalIssuerProfile::class, 'fiscal_issuer_profile_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(FiscalCustomerProfile::class, 'fiscal_customer_profile_id'); }
    public function items(): HasMany { return $this->hasMany(FiscalDocumentItem::class)->orderBy('sort_order'); }
    public function transmissions(): HasMany { return $this->hasMany(FiscalTransmission::class); }
    public function artifacts(): HasMany { return $this->hasMany(FiscalArtifact::class); }
}
