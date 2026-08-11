<?php

namespace App\Jobs;

use App\Modules\Finance\Actions\SyncChargeFromProvider;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class ProcessEfiPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $receiptId)
    {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(
        EfiPaymentProvider $provider,
        SyncChargeFromProvider $sync,
        DomainAudit $audit,
    ): void {
        $receipt = PaymentWebhookReceipt::query()->findOrFail($this->receiptId);

        $receipt->update([
            'status' => PaymentWebhookReceipt::STATUS_PROCESSING,
            'attempt_count' => $receipt->attempt_count + 1,
            'processing_started_at' => now(),
            'last_error' => null,
        ]);

        try {
            $token = Crypt::decryptString($receipt->token_encrypted);

            /*
             * A notificação pública é apenas um sinal. A decisão
             * financeira usa exclusivamente o histórico obtido por
             * OAuth + GET autenticado na Efí.
             */
            $events = $provider->fetchNotificationEvents($token);

            foreach ($events as $incoming) {
                if ($incoming->type === 'charge') {
                    $sync->handle($receipt, $incoming);
                }
            }

            $receipt->update([
                'status' => PaymentWebhookReceipt::STATUS_PROCESSED,
                'processed_at' => now(),
                'last_error' => null,
            ]);

            $audit->record(
                module: 'finance',
                action: 'payment_webhook.processed',
                entityType: 'payment_webhook_receipt',
                entityId: $receipt->id,
                metadata: ['provider' => 'efi'],
            );
        } catch (Throwable $exception) {
            $this->markFailed($receipt, $exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $receipt = PaymentWebhookReceipt::query()->find($this->receiptId);

        if ($receipt) {
            $this->markFailed($receipt, $exception);
        }
    }

    private function markFailed(
        PaymentWebhookReceipt $receipt,
        ?Throwable $exception,
    ): void {
        $message = $exception?->getMessage() ?? 'Falha não identificada.';
        $message = preg_replace(
            '/(authorization|bearer|token|secret)[=: ]+\S+/i',
            '$1=[REDACTED]',
            $message
        );
        $message = preg_replace(
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
            '[REDACTED_TOKEN]',
            $message
        );

        $receipt->update([
            'status' => PaymentWebhookReceipt::STATUS_FAILED,
            'last_error' => mb_substr($message, 0, 2000),
        ]);
    }
}
