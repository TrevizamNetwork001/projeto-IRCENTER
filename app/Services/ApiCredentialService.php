<?php

namespace App\Services;

use App\Models\ApiClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApiCredentialService
{
    public const TOKEN_PREFIX = 'irc_api_';

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * @return array{client: ApiClient, token: string}
     */
    public function create(
        string $name,
        ?CarbonInterface $expiresAt = null
    ): array {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(
                'O nome da credencial é obrigatório.'
            );
        }

        if ($expiresAt !== null && ! $expiresAt->isFuture()) {
            throw new InvalidArgumentException(
                'A expiração deve estar no futuro.'
            );
        }

        $token = self::TOKEN_PREFIX.bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use (
            $name,
            $expiresAt,
            $token,
            $tokenHash
        ): array {
            $client = ApiClient::create([
                'name' => $name,
                'identifier' => $this->newIdentifier(),
                'token_hash' => $tokenHash,
                'token_prefix' => substr($token, 0, 16),
                'is_active' => true,
                'expires_at' => $expiresAt,
            ]);

            $this->auditService->record(
                'api_client.created',
                $client,
                null,
                $this->auditState($client),
                $client->name
            );

            return [
                'client' => $client,
                'token' => $token,
            ];
        });
    }

    public function findByToken(string $token): ?ApiClient
    {
        if (! preg_match('/^irc_api_[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        $client = ApiClient::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if (
            $client === null
            || ! hash_equals($client->token_hash, $tokenHash)
        ) {
            return null;
        }

        return $client;
    }

    public function isValid(ApiClient $client): bool
    {
        return $client->is_active
            && $client->revoked_at === null
            && (
                $client->expires_at === null
                || $client->expires_at->isFuture()
            );
    }

    public function recordUsage(ApiClient $client, ?string $ip): void
    {
        $client->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->save();
    }

    public function revoke(ApiClient $client): ApiClient
    {
        return DB::transaction(function () use ($client): ApiClient {
            $oldState = $this->auditState($client);

            $client->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
            ])->save();

            $this->auditService->record(
                'api_client.revoked',
                $client,
                $oldState,
                $this->auditState($client),
                $client->name
            );

            return $client;
        });
    }

    public function reactivate(ApiClient $client): ApiClient
    {
        return DB::transaction(function () use ($client): ApiClient {
            $oldState = $this->auditState($client);

            $client->forceFill([
                'is_active' => true,
                'revoked_at' => null,
            ])->save();

            $this->auditService->record(
                'api_client.reactivated',
                $client,
                $oldState,
                $this->auditState($client),
                $client->name
            );

            return $client;
        });
    }

    private function newIdentifier(): string
    {
        do {
            $identifier = 'apic_'.bin2hex(random_bytes(16));
        } while (
            ApiClient::query()
                ->where('identifier', $identifier)
                ->exists()
        );

        return $identifier;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(ApiClient $client): array
    {
        return [
            'api_client_id' => $client->id,
            'identifier' => $client->identifier,
            'name' => $client->name,
            'token_prefix' => $client->token_prefix,
            'is_active' => $client->is_active,
            'expires_at' => $client->expires_at?->toIso8601String(),
            'revoked_at' => $client->revoked_at?->toIso8601String(),
        ];
    }
}
