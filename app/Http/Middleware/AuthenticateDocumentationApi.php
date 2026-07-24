<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDocumentationApi
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $configuredHash = trim(
            (string) config('documentation.api_token_hash')
        );

        $token = trim((string) $request->bearerToken());

        if (
            $configuredHash === ''
            || $token === ''
            || ! hash_equals(
                $configuredHash,
                hash('sha256', $token)
            )
        ) {
            return new JsonResponse(
                [
                    'message' => 'Credencial da API inválida.',
                ],
                401
            );
        }

        return $next($request);
    }
}
