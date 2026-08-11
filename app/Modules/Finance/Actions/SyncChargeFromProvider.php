<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Data\EfiNotificationEvent;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentProviderEvent;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Shared\Services\DomainAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SyncChargeFromProvider
{
    public function __construct(
        private readonly DomainAudit $audit,
    ) {
    }

    public function handle(
        PaymentWebhookReceipt $receipt,
        EfiNotificationEvent $incoming,
    ): Charge {
        return DB::connection('finance_fiscal')->transaction(function () use ($receipt, $incoming): Charge {
            $charge = Charge::query()
                ->where('provider', 'efi')
                ->where('provider_charge_id', $incoming->chargeId)
                ->lockForUpdate()
                ->first();

            if (! $charge) {
                throw new RuntimeException('Cobrança Efí local não encontrada.');
            }

            $event = PaymentProviderEvent::query()->firstOrCreate(
                [
                    'provider' => 'efi',
                    'provider_event_id' => $incoming->eventId,
                ],
                [
                    'webhook_receipt_id' => $receipt->id,
                    'charge_id' => $charge->id,
                    'notification_token_hash' => $receipt->token_hash,
                    'provider_charge_id' => $incoming->chargeId,
                    'event_type' => $incoming->type,
                    'status_current' => $incoming->currentStatus,
                    'status_previous' => $incoming->previousStatus,
                    'value_cents' => $incoming->valueCents,
                    'received_by_bank_at' => $incoming->receivedByBankAt,
                    'provider_created_at_raw' => $incoming->providerCreatedAtRaw,
                    'payload_json' => null,
                ]
            );

            if ($event->processed_at !== null) {
                return $charge;
            }

            $nextStatus = $this->safeStatus($charge->status, $incoming->normalizedStatus);
            $previousStatus = $charge->status;
            $eventIsOlder = ctype_digit($incoming->eventId)
                && ctype_digit((string) $charge->last_provider_event_id)
                && (int) $incoming->eventId <= (int) $charge->last_provider_event_id;

            if (! $eventIsOlder) {
                $charge->update([
                    'status' => $nextStatus,
                    'last_synced_at' => now(),
                    'last_provider_event_id' => $incoming->eventId,
                    'last_provider_event_at' => now(),
                ]);
            }

            $event->update(['processed_at' => now()]);

            $this->audit->record(
                module: 'finance',
                action: $eventIsOlder || $nextStatus !== $incoming->normalizedStatus
                    ? 'charge.sync_ignored'
                    : 'charge.synced',
                entityType: 'charge',
                entityId: $charge->id,
                metadata: [
                    'provider' => 'efi',
                    'provider_event_id' => $incoming->eventId,
                    'provider_charge_id' => $incoming->chargeId,
                    'remote_status' => $incoming->currentStatus,
                    'local_status' => $charge->status,
                ],
            );

            if (
                ! $eventIsOlder
                && $previousStatus !== Charge::STATUS_PAID
                && $nextStatus === Charge::STATUS_PAID
            ) {
                $this->audit->record(
                    module: 'finance',
                    action: 'charge.paid',
                    entityType: 'charge',
                    entityId: $charge->id,
                    metadata: [
                        'provider' => 'efi',
                        'provider_event_id' => $incoming->eventId,
                    ],
                );
            }

            if ($nextStatus === Charge::STATUS_PAID && ! $eventIsOlder) {
                $this->recordPayment($charge, $incoming);
            }

            return $charge->refresh();
        });
    }

    private function safeStatus(string $current, string $incoming): string
    {
        if ($current === Charge::STATUS_FAILED) {
            return $current;
        }

        if ($current === Charge::STATUS_PAID) {
            return $incoming === Charge::STATUS_FAILED
                ? Charge::STATUS_FAILED
                : Charge::STATUS_PAID;
        }

        if ($current === Charge::STATUS_CANCELED) {
            return $incoming === Charge::STATUS_PAID
                ? Charge::STATUS_PAID
                : Charge::STATUS_CANCELED;
        }

        if ($current === Charge::STATUS_OVERDUE && $incoming === Charge::STATUS_OPEN) {
            return Charge::STATUS_OVERDUE;
        }

        return $incoming;
    }

    private function recordPayment(Charge $charge, EfiNotificationEvent $incoming): void
    {
        if ($incoming->valueCents === null) {
            $this->recordAmountMismatch($charge, null);
            return;
        }

        $invoice = Invoice::query()->whereKey($charge->invoice_id)->lockForUpdate()->firstOrFail();
        $paidAt = $incoming->receivedByBankAt !== null
            ? CarbonImmutable::parse($incoming->receivedByBankAt, 'UTC')->startOfDay()
            : now();

        $payment = Payment::query()->firstOrCreate(
            ['charge_id' => $charge->id],
            [
                'invoice_id' => $invoice->id,
                'provider' => 'efi',
                'provider_payment_id' => $incoming->eventId,
                'amount' => number_format($incoming->valueCents / 100, 2, '.', ''),
                'currency' => $charge->currency,
                'paid_at' => $paidAt,
            ]
        );

        if ($payment->wasRecentlyCreated) {
            $this->audit->record(
                module: 'finance',
                action: 'payment.created',
                entityType: 'payment',
                entityId: $payment->id,
                metadata: [
                    'charge_id' => $charge->id,
                    'invoice_id' => $invoice->id,
                    'provider' => 'efi',
                    'provider_payment_id' => $incoming->eventId,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            );
        }

        $invoiceCents = Decimal::moneyToCents($invoice->total);

        if ($incoming->valueCents !== $invoiceCents) {
            $this->recordAmountMismatch($charge, $incoming->valueCents, $invoiceCents);
            return;
        }

        if ($invoice->status !== Invoice::STATUS_PAID) {
            $invoice->update(['status' => Invoice::STATUS_PAID]);

            $this->audit->record(
                module: 'finance',
                action: 'invoice.paid',
                entityType: 'invoice',
                entityId: $invoice->id,
                metadata: [
                    'charge_id' => $charge->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            );
        }
    }

    private function recordAmountMismatch(Charge $charge, ?int $received, ?int $expected = null): void
    {
        $this->audit->record(
            module: 'finance',
            action: 'payment.amount_mismatch',
            entityType: 'charge',
            entityId: $charge->id,
            metadata: [
                'received_cents' => $received,
                'expected_cents' => $expected ?? Decimal::moneyToCents($charge->amount),
            ],
        );
    }
}
