<?php

use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Profile\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login/mfa', [MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/login/mfa', [MfaChallengeController::class, 'store'])
        ->middleware('throttle:5,1')->name('mfa.challenge.store');
});

Route::middleware(['auth', 'user.active', 'password.changed'])->group(function (): void {
    Route::get('/profile/security', [SecurityController::class, 'index'])->name('profile.security');
    Route::post('/profile/security/confirm-password', [SecurityController::class, 'confirmPassword'])
        ->name('security.password.confirm');

    Route::middleware('password.recent')->group(function (): void {
        Route::get('/profile/security/mfa/enroll', [SecurityController::class, 'beginEnrollment'])->name('mfa.enroll');
        Route::post('/profile/security/mfa/confirm', [SecurityController::class, 'confirmEnrollment'])->name('mfa.confirm');
        Route::post('/profile/security/mfa/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('mfa.recovery.regenerate');
        Route::delete('/profile/security/mfa', [SecurityController::class, 'disable'])->name('mfa.disable');
        Route::delete('/profile/security/sessions/others', [SecurityController::class, 'revokeOtherSessions'])->name('sessions.others.destroy');
    });
});
