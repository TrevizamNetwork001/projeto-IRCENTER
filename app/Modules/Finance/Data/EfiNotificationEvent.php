<?php

namespace App\Modules\Finance\Data;

final readonly class EfiNotificationEvent
{
    public function __construct(
        public string $eventId,
        public string $chargeId,
        public string $type,
        public ?string $currentStatus,
        public ?string $previousStatus,
        public string $normalizedStatus,
        public ?int $valueCents,
        public ?string $receivedByBankAt,
        public ?string $providerCreatedAtRaw,
        public array $payload,
    ) {
    }
}
