<?php

namespace App\Modules\Finance\Infrastructure;

use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Data\PaymentChargeResult;
use InvalidArgumentException;
use RuntimeException;

final class FakePaymentProvider implements PaymentProvider
{
    /**
     * @var array<string, PaymentChargeResult>
     */
    private array $charges = [];

    public function key(): string
    {
        return 'fake';
    }

    public function capabilities(): array
    {
        return [
            'boleto',
            'pix',
            'boleto_pix',
            'webhook',
            'cancel',
        ];
    }

    public function isLive(): bool
    {
        return false;
    }

    public function createCharge(
        PaymentChargeRequest $request,
    ): PaymentChargeResult {
        $providerChargeId = 'fake_'.substr(
            hash(
                'sha256',
                $request->idempotencyKey
            ),
            0,
            24
        );

        if (isset($this->charges[$providerChargeId])) {
            return $this->charges[$providerChargeId];
        }

        $result = new PaymentChargeResult(
            providerChargeId: $providerChargeId,
            status: 'open',
        );

        $this->charges[$providerChargeId] = $result;

        return $result;
    }

    public function findCharge(
        string $providerChargeId,
    ): ?PaymentChargeResult {
        return $this->charges[$providerChargeId]
            ?? null;
    }

    public function cancelCharge(
        string $providerChargeId,
        string $idempotencyKey,
    ): PaymentChargeResult {
        $current = $this->findCharge(
            $providerChargeId
        );

        if (! $current) {
            throw new RuntimeException(
                'Cobrança fake não encontrada.'
            );
        }

        $result = new PaymentChargeResult(
            providerChargeId: $providerChargeId,
            status: 'canceled',
        );

        $this->charges[$providerChargeId] = $result;

        return $result;
    }

    public function validateWebhookRequest(
        string $rawBody,
        array $headers,
    ): bool {
        return ($headers['x-fake-signature'] ?? null)
            === 'valid';
    }

    public function parseWebhook(
        string $rawBody,
        array $headers,
    ): array {
        $decoded = json_decode(
            $rawBody,
            true
        );

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                'Webhook fake inválido.'
            );
        }

        return $decoded;
    }
}
