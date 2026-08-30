<?php

use App\Http\Controllers\Portal\InvoiceController;
use App\Http\Controllers\Portal\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:client')->group(function (): void {
    Route::get('/portal/login', [LoginController::class, 'create'])
        ->name('portal.login');

    Route::post('/portal/login', [LoginController::class, 'store'])
        ->name('portal.login.store');
});

Route::middleware('auth:client')
    ->prefix('portal')
    ->name('portal.')
    ->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])
            ->name('logout');

        Route::get('/faturas', [InvoiceController::class, 'index'])
            ->name('invoices.index');
    });
