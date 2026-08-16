<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function recordLegacyApiTokenUsage(Request $request): AuditLog
    {
        $userAgent = $request->userAgent();

        if (is_string($userAgent)) {
            $userAgent = mb_substr(
                preg_replace('/[\x00-\x1F\x7F]/u', '', $userAgent) ?? '',
                0,
                500
            );
        }

        return AuditLog::create([
            'user_id' => null,
            'action' => 'api_client.legacy_token_used',
            'resource_type' => 'DocumentationApi',
            'resource_id' => null,
            'resource_label' => 'Legacy API token (deprecated)',
            'old_values' => null,
            'new_values' => [
                'request_id' => (string) $request->attributes->get(
                    'request_id'
                ),
                'method' => $request->method(),
                'route' => '/'.$request->path(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        Model $resource,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $label = null,
        ?Request $request = null
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'resource_type' => class_basename($resource),
            'resource_id' => $resource->getKey(),
            'resource_label' => $label ?? $this->label($resource),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ([
            'password',
            'remember_token',
            'token',
            'token_hash',
            'secret',
            'api_key',
        ] as $sensitiveKey) {
            unset($values[$sensitiveKey]);
        }

        return $values;
    }

    private function label(Model $resource): string
    {
        foreach ([
            'legal_name',
            'trade_name',
            'name',
            'prefix',
            'email',
        ] as $attribute) {
            $value = $resource->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        if ($resource->getAttribute('asn')) {
            return 'AS'.$resource->getAttribute('asn');
        }

        return class_basename($resource).' #'.$resource->getKey();
    }
}
