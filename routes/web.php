<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutonomousSystemController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrefixController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::patch(
        '/clients/{client}/toggle-active',
        [ClientController::class, 'toggleActive']
    )->name('clients.toggle-active');

    Route::resource('clients', ClientController::class);

    Route::patch(
        '/autonomous-systems/{autonomous_system}/toggle-active',
        [AutonomousSystemController::class, 'toggleActive']
    )->name('autonomous-systems.toggle-active');

    Route::resource(
        'autonomous-systems',
        AutonomousSystemController::class
    );

    Route::get('/prefixes/ipv4', [PrefixController::class, 'ipv4'])
        ->name('prefixes.ipv4');

    Route::get('/prefixes/ipv6', [PrefixController::class, 'ipv6'])
        ->name('prefixes.ipv6');

    Route::patch(
        '/prefixes/{prefix}/toggle-active',
        [PrefixController::class, 'toggleActive']
    )->name('prefixes.toggle-active');

    Route::resource('prefixes', PrefixController::class);

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
