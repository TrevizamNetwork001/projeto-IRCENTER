<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\AuthenticationAudit;
use App\Services\Identity\MfaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MfaChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.mfa_pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function store(
        Request $request,
        MfaManager $manager,
        AuthenticationAudit $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);
        $pendingUserId = $request->session()->get('auth.mfa_pending_user_id');
        $throttleKey = $this->throttleKey($request, $pendingUserId);
        $accountThrottleKey = $this->accountThrottleKey($pendingUserId);

        // Trava por usuário pendente+IP (força bruta de um ponto) e por
        // usuário pendente isolado (força bruta distribuída por múltiplos
        // IPs contra o mesmo desafio de MFA).
        if (
            RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($accountThrottleKey, self::MAX_ATTEMPTS * 4)
        ) {
            $request->session()->forget(['auth.mfa_pending_user_id', 'auth.mfa_remember']);
            $seconds = max(
                RateLimiter::availableIn($throttleKey),
                RateLimiter::availableIn($accountThrottleKey),
            );

            throw ValidationException::withMessages([
                'code' => "Muitas tentativas. Faça login novamente em {$seconds} segundos.",
            ]);
        }

        $user = User::query()->find($pendingUserId);

        if (! $user || ! $user->active) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            RateLimiter::hit($accountThrottleKey, self::DECAY_SECONDS * 15);
            $request->session()->forget(['auth.mfa_pending_user_id', 'auth.mfa_remember']);
            throw ValidationException::withMessages(['code' => 'Desafio inválido ou expirado.']);
        }

        $code = trim($validated['code']);
        if (! $manager->verifyTotp($user, $code) && ! $manager->useRecoveryCode($user, $code)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            RateLimiter::hit($accountThrottleKey, self::DECAY_SECONDS * 15);
            throw ValidationException::withMessages(['code' => 'Código de autenticação inválido.']);
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($accountThrottleKey);
        $remember = (bool) $request->session()->pull('auth.mfa_remember', false);
        $request->session()->forget('auth.mfa_pending_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $audit->record($user, 'identity.login.mfa_succeeded', $request);

        return redirect()->intended($user->must_change_password
            ? route('password.change.edit')
            : route('dashboard'));
    }

    private function throttleKey(Request $request, mixed $pendingUserId): string
    {
        return Str::transliterate(($pendingUserId ?? 'anon').'|'.$request->ip());
    }

    private function accountThrottleKey(mixed $pendingUserId): string
    {
        return 'mfa-account|'.($pendingUserId ?? 'anon');
    }
}
