<?php

use App\Http\Middleware\AuthenticateDocumentationApi;
use App\Http\Middleware\EnsurePasswordWasChanged;
use App\Http\Middleware\RequestLogContext;
use App\Http\Middleware\SecurityHeaders;
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
            'documentation.api' =>
                AuthenticateDocumentationApi::class,
            'password.changed' =>
                EnsurePasswordWasChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response): Response {
            $requestId = request()->attributes->get('request_id');

            if (is_string($requestId)) {
                $response->headers->set('X-Request-ID', $requestId);
            }

            return $response;
        });
    })->create();
