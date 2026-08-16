<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Support\DocumentationApiScope;
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
        ?CarbonInterface $expiresAt = null,
        array $scopes = []
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
        $scopes = $this->normalizeScopes($scopes);

        return DB::transaction(function () use (
            $name,
            $expiresAt,
            $token,
            $tokenHash,
            $scopes
        ): array {
            $client = ApiClient::create([
                'name' => $name,
                'identifier' => $this->newIdentifier(),
                'token_hash' => $tokenHash,
                'token_prefix' => substr($token, 0, 16),
                'is_active' => true,
                'expires_at' => $expiresAt,
                'scopes' => $scopes,
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

    /**
     * @param  list<string>  $scopes
     */
    public function updateScopes(ApiClient $client, array $scopes): ApiClient
    {
        $scopes = $this->normalizeScopes($scopes);

        return DB::transaction(function () use ($client, $scopes): ApiClient {
            $oldValues = [
                'api_client_id' => $client->id,
                'identifier' => $client->identifier,
                'scopes' => $client->scopes ?? [],
            ];

            $client->forceFill(['scopes' => $scopes])->save();

            $this->auditService->record(
                'api_client.scopes_updated',
                $client,
                $oldValues,
                [
                    'api_client_id' => $client->id,
                    'identifier' => $client->identifier,
                    'scopes' => $client->scopes,
                ],
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
     * @param  array<array-key, mixed>  $scopes
     * @return list<string>
     */
    private function normalizeScopes(array $scopes): array
    {
        foreach ($scopes as $scope) {
            if (! is_string($scope)) {
                throw new InvalidArgumentException(
                    'Um ou mais scopes são inválidos.'
                );
            }
        }

        $normalized = array_values(array_unique(array_map(
            fn (string $scope): string => trim($scope),
            $scopes
        )));

        if (
            in_array('', $normalized, true)
            || array_diff($normalized, DocumentationApiScope::all()) !== []
        ) {
            throw new InvalidArgumentException(
                'Um ou mais scopes são inválidos.'
            );
        }

        return array_values(array_filter(
            DocumentationApiScope::all(),
            fn (string $scope): bool => in_array($scope, $normalized, true)
        ));
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
            'scopes' => $client->scopes ?? [],
        ];
    }
}
