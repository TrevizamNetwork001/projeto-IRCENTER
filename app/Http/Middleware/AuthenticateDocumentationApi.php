<?php

namespace App\Http\Middleware;

use App\Services\ApiCredentialService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDocumentationApi
{
    public function __construct(
        private readonly ApiCredentialService $credentialService
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

            // Temporary fallback for the legacy documentation API token.
            $legacyHash = trim(
                (string) config('documentation.api_token_hash')
            );

            if (
                $legacyHash !== ''
                && hash_equals($legacyHash, hash('sha256', $token))
            ) {
                $request->attributes->set('api_client', 'legacy');

                return $next($request);
            }
        }

        return new JsonResponse(
            [
                'message' => 'Credencial da API inválida.',
            ],
            401
        );
    }
}
