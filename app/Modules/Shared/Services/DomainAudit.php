<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\DomainAuditEvent;
use Illuminate\Support\Str;

final class DomainAudit
{
    private const SENSITIVE_KEYS = [
        'password',
        'secret',
        'token',
        'authorization',
        'api_key',
        'private_key',
        'certificate_password',
    ];

    public function record(
        string $module,
        string $action,
        ?int $actorUserId = null,
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): DomainAuditEvent {
        return DomainAuditEvent::query()->create([
            'module' => $module,
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null
                ? null
                : (string) $entityId,
            'correlation_id' => $correlationId
                ?: (string) Str::uuid(),
            'metadata' => $this->sanitize($metadata),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $value): array
    {
        foreach ($value as $key => $item) {
            $normalized = strtolower((string) $key);

            if (in_array(
                $normalized,
                self::SENSITIVE_KEYS,
                true
            )) {
                $value[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->sanitize($item);
            }
        }

        return $value;
    }
}
