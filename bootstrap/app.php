<?php

use App\Http\Middleware\AuthenticateDocumentationApi;
use App\Http\Middleware\EnsureFiscalEnabled;
use App\Http\Middleware\EnsurePasswordWasChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequestLogContext;
use App\Http\Middleware\RequireDocumentationApiScope;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleDocumentationApiClient;
use App\Http\Middleware\ValidateEfiWebhookCallback;
use App\Support\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestLogContext::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'documentation.api' => AuthenticateDocumentationApi::class,
            'documentation.scope' => RequireDocumentationApiScope::class,
            'documentation.client.throttle' => ThrottleDocumentationApiClient::class,
            'password.changed' => EnsurePasswordWasChanged::class,
            'user.active' => EnsureUserIsActive::class,
            'fiscal.enabled' => EnsureFiscalEnabled::class,
            'efi.webhook.callback' => ValidateEfiWebhookCallback::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response): Response {
            $request = request();
            $requestId = request()->attributes->get('request_id');

            if ($request->is('api/v1/documentation/*')) {
                $status = $response->getStatusCode();
                $headers = $response->headers->all();

                if ($status === 422) {
                    $payload = json_decode(
                        (string) $response->getContent(),
                        true,
                    );

                    return ApiErrorResponse::make(
                        code: 'validation_error',
                        message: 'Os dados informados são inválidos.',
                        status: 422,
                        request: $request,
                        details: is_array($payload['errors'] ?? null)
                            ? $payload['errors']
                            : [],
                        headers: $headers,
                    );
                }

                if ($status === 404) {
                    return ApiErrorResponse::make(
                        code: 'resource_not_found',
                        message: 'Recurso não encontrado.',
                        status: 404,
                        request: $request,
                        headers: $headers,
                    );
                }

                if ($status === 429) {
                    return ApiErrorResponse::make(
                        code: 'rate_limit_exceeded',
                        message: 'Limite de requisições excedido.',
                        status: 429,
                        request: $request,
                        headers: $headers,
                    );
                }

                if ($status >= 500) {
                    return ApiErrorResponse::make(
                        code: 'internal_error',
                        message: 'Não foi possível processar a solicitação.',
                        status: 500,
                        request: $request,
                    );
                }
            }

            if (is_string($requestId)) {
                $response->headers->set('X-Request-ID', $requestId);
            }

            return $response;
        });
    })->create();
