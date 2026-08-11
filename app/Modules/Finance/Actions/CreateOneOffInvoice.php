<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingItem;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Shared\Contracts\ClientDirectory;
use App\Modules\Shared\Services\DomainAudit;
use App\Support\BusinessClock;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateOneOffInvoice
{
    public function __construct(
        private readonly ClientDirectory $clients,
        private readonly DomainAudit $audit,
        private readonly BusinessClock $clock,
    ) {
    }

    public function handle(int $clientId, int $billingItemId, string $quantity, string $unitAmount, string $dueOn, ?int $actorUserId = null): Invoice
    {
        $client = $this->clients->find($clientId);
        if (! $client || ! $client->active) {
            throw new DomainException('Cliente ativo não encontrado.');
        }
        $catalogItem = BillingItem::query()->whereKey($billingItemId)->where('active', true)->first();
        if (! $catalogItem) {
            throw new DomainException('Item de cobrança ativo não encontrado.');
        }
        $quantity = Decimal::quantity($quantity);
        $unitAmount = Decimal::money($unitAmount);
        $total = Decimal::multiplyQuantityByMoney($quantity, $unitAmount);

        return DB::connection('finance_fiscal')->transaction(function () use ($client, $catalogItem, $quantity, $unitAmount, $dueOn, $total, $actorUserId): Invoice {
            $invoice = Invoice::query()->create([
                'core_client_id' => $client->id,
                'client_code_snapshot' => $client->clientCode,
                'client_legal_name_snapshot' => $client->legalName,
                'client_trade_name_snapshot' => $client->tradeName,
                'client_document_snapshot' => $client->document,
                'billing_email_snapshot' => $client->email,
                'client_phone_snapshot' => $client->phone,
                'client_postal_code_snapshot' => $client->postalCode,
                'client_street_snapshot' => $client->street,
                'client_address_number_snapshot' => $client->addressNumber,
                'client_address_complement_snapshot' => $client->addressComplement,
                'client_district_snapshot' => $client->district,
                'client_city_snapshot' => $client->city,
                'client_state_snapshot' => $client->state,
                'client_country_snapshot' => $client->country,
                'source' => Invoice::SOURCE_ONE_OFF,
                'issued_on' => $this->clock->today()->toDateString(),
                'due_on' => $dueOn,
                'currency' => 'BRL', 'status' => Invoice::STATUS_OPEN,
                'subtotal' => $total, 'discount' => '0.00', 'total' => $total,
            ]);
            $invoice->items()->create([
                'service_code' => 'CAT-'.$catalogItem->id,
                'description' => $catalogItem->name,
                'quantity' => $quantity, 'unit_amount' => $unitAmount,
                'line_total' => $total, 'sort_order' => 0,
            ]);
            $this->audit->record('finance', 'invoice.one_off_created', $actorUserId, 'invoice', $invoice->id, ['billing_item_id' => $catalogItem->id, 'total' => $total]);
            return $invoice->load('items');
        });
    }
}
