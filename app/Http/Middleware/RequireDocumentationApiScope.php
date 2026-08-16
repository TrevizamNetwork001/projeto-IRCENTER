<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Support\ApiErrorResponse;
use App\Support\DocumentationApiScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDocumentationApiScope
{
    public function handle(
        Request $request,
        Closure $next,
        string $requiredScope
    ): Response {
        if (! in_array($requiredScope, DocumentationApiScope::all(), true)) {
            abort(500, 'Scope de rota inválido.');
        }

        $apiClient = $request->attributes->get('api_client');

        // LEGACY COMPATIBILITY: remove with the global token fallback.
        if ($apiClient === 'legacy') {
            return $next($request);
        }

        if (
            $apiClient instanceof ApiClient
            && in_array($requiredScope, $apiClient->scopes ?? [], true)
        ) {
            return $next($request);
        }

        return ApiErrorResponse::make(
            code: 'insufficient_scope',
            message: 'Acesso não autorizado para este recurso.',
            status: 403,
            request: $request,
        );
    }
}
