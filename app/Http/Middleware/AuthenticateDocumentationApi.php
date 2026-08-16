<?php

namespace App\Http\Middleware;

use App\Services\ApiCredentialService;
use App\Services\AuditService;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDocumentationApi
{
    public function __construct(
        private readonly ApiCredentialService $credentialService,
        private readonly AuditService $auditService,
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $token = trim((string) $request->bearerToken());

        if ($token !== '') {
            $apiClient = $this->credentialService->findByToken($token);

            if (
                $apiClient !== null
                && $this->credentialService->isValid($apiClient)
            ) {
                $this->credentialService->recordUsage(
                    $apiClient,
                    $request->ip()
                );

                $request->attributes->set('api_client', $apiClient);

                return $next($request);
            }

            if (
                (bool) config('documentation.legacy_token_enabled', false)
                && $this->matchesLegacyToken($token)
            ) {
                $this->auditService->recordLegacyApiTokenUsage($request);
                $request->attributes->set('api_client', 'legacy');

                return $next($request);
            }
        }

        return ApiErrorResponse::make(
            code: $token === ''
                ? 'authentication_required'
                : 'invalid_token',
            message: $token === ''
                ? 'Credencial de acesso não informada.'
                : 'Credencial de acesso inválida.',
            status: 401,
            request: $request,
        );
    }

    private function matchesLegacyToken(string $token): bool
    {
        $legacyHash = trim(
            (string) config('documentation.api_token_hash')
        );

        return preg_match('/^[a-f0-9]{64}$/D', $legacyHash) === 1
            && hash_equals($legacyHash, hash('sha256', $token));
    }
}
