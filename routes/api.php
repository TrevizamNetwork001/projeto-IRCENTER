<?php

use App\Http\Controllers\Api\V1\DocumentationResourceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/documentation')
    ->middleware([
        'documentation.api',
        'throttle:documentation-api',
    ])
    ->group(function (): void {
        Route::get(
            '/clients',
            [DocumentationResourceController::class, 'clients']
        )->name('api.documentation.clients.index');

        Route::get(
            '/clients/{client}',
            [DocumentationResourceController::class, 'client']
        )->name('api.documentation.clients.show');

        Route::get(
            '/users',
            [DocumentationResourceController::class, 'users']
        )->name('api.documentation.users.index');

        Route::get(
            '/autonomous-systems',
            [
                DocumentationResourceController::class,
                'autonomousSystems',
            ]
        )->name('api.documentation.autonomous-systems.index');

        Route::get(
            '/prefixes',
            [DocumentationResourceController::class, 'prefixes']
        )->name('api.documentation.prefixes.index');
    });
