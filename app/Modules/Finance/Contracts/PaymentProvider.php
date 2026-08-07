<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Data\PaymentChargeResult;

interface PaymentProvider
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    public function isLive(): bool;

    public function createCharge(
        PaymentChargeRequest $request,
    ): PaymentChargeResult;

    public function findCharge(
        string $providerChargeId,
    ): ?PaymentChargeResult;

    public function cancelCharge(
        string $providerChargeId,
        string $idempotencyKey,
    ): PaymentChargeResult;

    public function verifyWebhook(
        string $rawBody,
        array $headers,
    ): bool;

    /**
     * @return array<string, mixed>
     */
    public function parseWebhook(
        string $rawBody,
        array $headers,
    ): array;
}
