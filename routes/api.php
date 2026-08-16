<?php

use App\Http\Controllers\Api\V1\DocumentationResourceController;
use App\Http\Controllers\Api\V1\EfiPaymentWebhookController;
use App\Support\DocumentationApiScope;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/documentation')
    ->middleware([
        'throttle:documentation-api',
        'documentation.api',
    ])
    ->group(function (): void {
        Route::get(
            '/clients',
            [DocumentationResourceController::class, 'clients']
        )
            ->middleware(
                'documentation.scope:'.DocumentationApiScope::CLIENTS_READ
            )
            ->name('api.documentation.clients.index');

        Route::get(
            '/clients/{client}',
            [DocumentationResourceController::class, 'client']
        )
            ->middleware(
                'documentation.scope:'.DocumentationApiScope::CLIENTS_READ
            )
            ->name('api.documentation.clients.show');

        Route::get(
            '/users',
            [DocumentationResourceController::class, 'users']
        )
            ->middleware(
                'documentation.scope:'.DocumentationApiScope::USERS_READ
            )
            ->name('api.documentation.users.index');

        Route::get(
            '/autonomous-systems',
            [
                DocumentationResourceController::class,
                'autonomousSystems',
            ]
        )
            ->middleware(
                'documentation.scope:'.DocumentationApiScope::NETWORK_READ
            )
            ->name('api.documentation.autonomous-systems.index');

        Route::get(
            '/prefixes',
            [DocumentationResourceController::class, 'prefixes']
        )
            ->middleware(
                'documentation.scope:'.DocumentationApiScope::NETWORK_READ
            )
            ->name('api.documentation.prefixes.index');
    });

Route::post(
    '/v1/webhooks/payments/efi/{callbackSecret}',
    EfiPaymentWebhookController::class
)
    ->where('callbackSecret', '[a-f0-9]{64}')
    ->middleware([
        'throttle:efi-payment-webhook',
        'efi.webhook.callback',
    ])
    ->name('api.webhooks.payments.efi');

/* Rota transitória, desabilitada por padrão, para troca controlada na Efí. */
Route::post(
    '/v1/webhooks/payments/efi',
    EfiPaymentWebhookController::class
)
    ->middleware([
        'throttle:efi-payment-webhook',
        'efi.webhook.callback',
    ])
    ->name('api.webhooks.payments.efi.legacy');
