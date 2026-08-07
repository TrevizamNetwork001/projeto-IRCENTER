<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateChargeForInvoice
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        int $invoiceId,
        string $method,
        ?int $actorUserId = null,
    ): Charge {
        if (
            ! config(
                'finance_fiscal.finance.enabled',
                false
            )
        ) {
            throw new DomainException(
                'Módulo financeiro está desabilitado.'
            );
        }

        if (
            ! in_array(
                $method,
                [
                    Charge::METHOD_BOLETO,
                    Charge::METHOD_PIX,
                    Charge::METHOD_BOLETO_PIX,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Método de cobrança não suportado.'
            );
        }

        if (
            ! in_array(
                $method,
                $this->provider->capabilities(),
                true
            )
        ) {
            throw new DomainException(
                'Provider não suporta o método solicitado.'
            );
        }

        if (
            $this->provider->isLive()
            && ! config(
                'finance_fiscal.finance.'
                .'payment_live_enabled',
                false
            )
        ) {
            throw new DomainException(
                'Cobrança live está desabilitada.'
            );
        }

        return DB::connection('finance_fiscal')
            ->transaction(function () use (
                $invoiceId,
                $method,
                $actorUserId,
            ): Charge {
                $invoice = Invoice::query()
                    ->whereKey($invoiceId)
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    throw new DomainException(
                        'Fatura não encontrada.'
                    );
                }

                if (
                    $invoice->status
                    !== Invoice::STATUS_OPEN
                ) {
                    throw new DomainException(
                        'Somente fatura aberta pode gerar cobrança.'
                    );
                }

                $idempotencyKey = sprintf(
                    'invoice:%s:provider:%s:method:%s',
                    $invoice->public_id,
                    $this->provider->key(),
                    $method
                );

                $existing = Charge::query()
                    ->where(
                        'idempotency_key',
                        $idempotencyKey
                    )
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $request = new PaymentChargeRequest(
                    invoicePublicId:
                        $invoice->public_id,

                    idempotencyKey:
                        $idempotencyKey,

                    method:
                        $method,

                    amount:
                        Decimal::money(
                            $invoice->total
                        ),

                    currency:
                        $invoice->currency,

                    dueOn:
                        $invoice->due_on
                            ->toDateString(),

                    payerName:
                        $invoice
                            ->client_legal_name_snapshot,

                    payerDocument:
                        $invoice
                            ->client_document_snapshot,

                    payerEmail:
                        $invoice->billing_email_snapshot,

                    payerPhone:
                        $invoice->client_phone_snapshot,

                    payerPostalCode:
                        $invoice->client_postal_code_snapshot,

                    payerStreet:
                        $invoice->client_street_snapshot,

                    payerAddressNumber:
                        $invoice->client_address_number_snapshot,

                    payerAddressComplement:
                        $invoice
                            ->client_address_complement_snapshot,

                    payerDistrict:
                        $invoice->client_district_snapshot,

                    payerCity:
                        $invoice->client_city_snapshot,

                    payerState:
                        $invoice->client_state_snapshot,

                    payerCountry:
                        $invoice->client_country_snapshot,
                );

                $result = $this->provider
                    ->createCharge($request);

                $charge = Charge::query()->create([
                    'invoice_id' =>
                        $invoice->id,

                    'provider' =>
                        $this->provider->key(),

                    'method' => $method,

                    'status' =>
                        $result->status,

                    'idempotency_key' =>
                        $idempotencyKey,

                    'provider_charge_id' =>
                        $result->providerChargeId,

                    'amount' =>
                        Decimal::money(
                            $invoice->total
                        ),

                    'currency' =>
                        $invoice->currency,

                    'due_on' =>
                        $invoice->due_on
                            ->toDateString(),

                    'provider_checkout_url' =>
                        $result->checkoutUrl,

                    'provider_pix_copy_paste' =>
                        $result->pixCopyPaste,

                    'provider_created_at' =>
                        now(),

                    'last_synced_at' =>
                        now(),
                ]);

                $this->audit->record(
                    module: 'finance',
                    action: 'charge.created',
                    actorUserId: $actorUserId,
                    entityType: 'charge',
                    entityId: $charge->id,
                    metadata: [
                        'public_id' =>
                            $charge->public_id,

                        'invoice_id' =>
                            $invoice->id,

                        'provider' =>
                            $charge->provider,

                        'method' =>
                            $charge->method,

                        'status' =>
                            $charge->status,

                        'amount' =>
                            $charge->amount,
                    ],
                );

                return $charge;
            });
    }
}
