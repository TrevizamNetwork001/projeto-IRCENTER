<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutonomousSystemController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IrrObjectController;
use App\Http\Controllers\IrrWorkflowController;
use App\Http\Controllers\PrefixController;
use App\Http\Controllers\RpkiValidationController;
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

    Route::patch(
        '/irr-objects/{irr_object}/toggle-active',
        [IrrObjectController::class, 'toggleActive']
    )->name('irr-objects.toggle-active');

    Route::resource('irr-objects', IrrObjectController::class);

    Route::get(
        '/irr-assistant',
        [IrrWorkflowController::class, 'index']
    )->name('irr-workflows.index');

    Route::get(
        '/irr-assistant/create',
        [IrrWorkflowController::class, 'create']
    )->name('irr-workflows.create');

    Route::post(
        '/irr-assistant',
        [IrrWorkflowController::class, 'store']
    )->name('irr-workflows.store');

    Route::get(
        '/irr-assistant/{irrWorkflow}',
        [IrrWorkflowController::class, 'show']
    )->name('irr-workflows.show');

    Route::patch(
        '/irr-assistant/{irrWorkflow}/prefixes/{workflowPrefix}',
        [IrrWorkflowController::class, 'updatePrefixPolicy']
    )->name('irr-workflows.prefixes.update');

    Route::post(
        '/irr-assistant/{irrWorkflow}/steps/{step}/sent',
        [IrrWorkflowController::class, 'markSent']
    )->name('irr-workflows.steps.sent');

    Route::post(
        '/irr-assistant/{irrWorkflow}/steps/{step}/confirm',
        [IrrWorkflowController::class, 'confirm']
    )->name('irr-workflows.steps.confirm');

    Route::get(
        '/rpki',
        [RpkiValidationController::class, 'index']
    )->name('rpki.index');

    Route::get(
        '/rpki/prefixes/{prefix}/history',
        [RpkiValidationController::class, 'history']
    )->name('rpki.history');

    Route::post(
        '/prefixes/{prefix}/rpki-validation',
        [RpkiValidationController::class, 'store']
    )->name('prefixes.rpki-validation.store');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
