<?php

namespace App\Jobs;

use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\PaymentProviderEvent;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ProcessEfiPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public int $receiptId,
    ) {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [
            10,
            30,
            120,
            300,
        ];
    }

    public function handle(
        EfiPaymentProvider $provider,
        DomainAudit $audit,
    ): void {
        $receipt =
            PaymentWebhookReceipt::query()
                ->findOrFail(
                    $this->receiptId
                );

        $receipt->update([
            'status' =>
                PaymentWebhookReceipt::
                    STATUS_PROCESSING,

            'attempt_count' =>
                $receipt->attempt_count + 1,

            'processing_started_at' =>
                now(),

            'last_error' =>
                null,
        ]);

        $token = Crypt::decryptString(
            $receipt->token_encrypted
        );

        /*
         * Esta consulta é o passo que a Efí utiliza
         * para considerar a notificação consumida.
         */
        $events =
            $provider->fetchNotificationEvents(
                $token
            );

        DB::connection('finance_fiscal')
            ->transaction(function () use (
                $receipt,
                $events,
                $audit,
            ): void {
                foreach ($events as $incoming) {
                    if (
                        $incoming->type !== 'charge'
                    ) {
                        continue;
                    }

                    $charge = Charge::query()
                        ->where(
                            'provider',
                            'efi'
                        )
                        ->where(
                            'provider_charge_id',
                            $incoming->chargeId
                        )
                        ->lockForUpdate()
                        ->first();

                    /*
                     * Pode ocorrer se o callback chegar
                     * antes do commit da criação local.
                     * O Job será tentado novamente.
                     */
                    if (! $charge) {
                        throw new RuntimeException(
                            'Cobrança Efí local não encontrada.'
                        );
                    }

                    $event =
                        PaymentProviderEvent::query()
                            ->firstOrCreate(
                                [
                                    'provider' =>
                                        'efi',

                                    'notification_token_hash' =>
                                        $receipt
                                            ->token_hash,

                                    'provider_event_id' =>
                                        $incoming
                                            ->eventId,
                                ],
                                [
                                    'webhook_receipt_id' =>
                                        $receipt->id,

                                    'charge_id' =>
                                        $charge->id,

                                    'provider_charge_id' =>
                                        $incoming
                                            ->chargeId,

                                    'event_type' =>
                                        $incoming->type,

                                    'status_current' =>
                                        $incoming
                                            ->currentStatus,

                                    'status_previous' =>
                                        $incoming
                                            ->previousStatus,

                                    'value_cents' =>
                                        $incoming
                                            ->valueCents,

                                    'received_by_bank_at' =>
                                        $incoming
                                            ->receivedByBankAt,

                                    'provider_created_at_raw' =>
                                        $incoming
                                            ->providerCreatedAtRaw,

                                    'payload_json' =>
                                        $incoming
                                            ->payload,
                                ]
                            );

                    $lastId = (
                        $charge
                            ->last_provider_event_id
                        !== null
                    )
                        ? (int) $charge
                            ->last_provider_event_id
                        : 0;

                    $incomingId =
                        (int) $incoming->eventId;

                    /*
                     * Replay de evento antigo nunca
                     * regride o estado da cobrança.
                     */
                    if ($incomingId <= $lastId) {
                        if (
                            $event->processed_at
                            === null
                        ) {
                            $event->update([
                                'processed_at' =>
                                    now(),
                            ]);
                        }

                        continue;
                    }

                    $charge->update([
                        'status' =>
                            $incoming
                                ->normalizedStatus,

                        'last_synced_at' =>
                            now(),

                        'last_provider_event_id' =>
                            $incoming->eventId,

                        'last_provider_event_at' =>
                            now(),
                    ]);

                    $event->update([
                        'processed_at' => now(),
                    ]);

                    $audit->record(
                        module: 'finance',

                        action:
                            'charge.provider_event_applied',

                        entityType:
                            'charge',

                        entityId:
                            $charge->id,

                        metadata: [
                            'provider' =>
                                'efi',

                            'provider_event_id' =>
                                $incoming
                                    ->eventId,

                            'provider_charge_id' =>
                                $incoming
                                    ->chargeId,

                            'status' =>
                                $incoming
                                    ->normalizedStatus,

                            'value_cents' =>
                                $incoming
                                    ->valueCents,
                        ],
                    );
                }

                $receipt->update([
                    'status' =>
                        PaymentWebhookReceipt::
                            STATUS_PROCESSED,

                    'processed_at' =>
                        now(),

                    'last_error' =>
                        null,
                ]);
            });
    }

    public function failed(
        ?Throwable $exception,
    ): void {
        $receipt =
            PaymentWebhookReceipt::query()
                ->find(
                    $this->receiptId
                );

        if (! $receipt) {
            return;
        }

        $message = $exception?->getMessage()
            ?? 'Falha não identificada.';

        /*
         * Não permite que tokens/segredos sejam
         * persistidos acidentalmente no erro.
         */
        $message = preg_replace(
            '/(authorization|bearer|token|secret)'
            .'[=: ]+\S+/i',
            '$1=[REDACTED]',
            $message
        );

        /*
         * Tokens de notificação Efí são UUID-like.
         * Evita persistência acidental em mensagens
         * retornadas por clientes HTTP/exceptions.
         */
        $message = preg_replace(
            '/\b[0-9a-f]{8}-'
            .'[0-9a-f]{4}-'
            .'[0-9a-f]{4}-'
            .'[0-9a-f]{4}-'
            .'[0-9a-f]{12}\b/i',
            '[REDACTED_TOKEN]',
            $message
        );

        $receipt->update([
            'status' =>
                PaymentWebhookReceipt::
                    STATUS_FAILED,

            'last_error' =>
                mb_substr(
                    $message,
                    0,
                    2000
                ),
        ]);
    }
}
