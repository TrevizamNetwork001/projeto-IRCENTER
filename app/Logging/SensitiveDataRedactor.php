<?php

namespace App\Logging;

use Stringable;
use Throwable;

final class SensitiveDataRedactor
{
    private const SENSITIVE_KEY_PARTS = [
        'password', 'authorization', 'cookie', 'token', 'secret',
        'api_key', 'hmac', 'signature', 'private_key', 'certificate',
        'database_url', 'postgres_password', 'pix_copy_paste',
    ];

    public function context(array $context): array
    {
        return $this->array($context);
    }

    public function text(string $value): string
    {
        $value = preg_replace(
            '/-----BEGIN [^-]*PRIVATE KEY-----.*?'
                .'-----END [^-]*PRIVATE KEY-----/si',
            '[REDACTED PRIVATE KEY]',
            $value,
        ) ?? '[REDACTED]';

        $value = preg_replace(
            '/\bBearer\s+[^\s,;]+/i',
            'Bearer [REDACTED]',
            $value,
        ) ?? '[REDACTED]';

        return preg_replace(
            '/\b(password(?:_confirmation)?|current_password|'
                .'authorization|cookie|set-cookie|(?:api_|access_|refresh_)?token|'
                .'client_secret|secret|hmac|signature|private_key|database_url|'
                .'postgres_password|pix_copy_paste)\b\s*[:=]\s*'
                .'(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i',
            '$1=[REDACTED]',
            $value,
        ) ?? '[REDACTED]';
    }

    private function array(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $value[$key] = '[REDACTED]';
                continue;
            }

            $value[$key] = $this->value($item);
        }

        return $value;
    }

    private function value(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->array($value);
        }
        if (is_string($value)) {
            return $this->text($value);
        }
        if ($value instanceof Throwable) {
            return [
                'type' => $value::class,
                'code' => $value->getCode(),
                'message' => $this->text($value->getMessage()),
            ];
        }
        if ($value instanceof Stringable) {
            return $this->text((string) $value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower(str_replace('-', '_', $key));

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }
}
