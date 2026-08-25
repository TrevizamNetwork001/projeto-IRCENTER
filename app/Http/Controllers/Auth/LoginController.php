<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Identity\AuthenticationAudit;
use App\Services\Identity\MfaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        MfaManager $mfa,
        AuthenticationAudit $audit,
    ): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        if ($mfa->isEnabled($user)) {
            $request->session()->put([
                'auth.mfa_pending_user_id' => $user->id,
                'auth.mfa_remember' => $request->boolean('remember'),
            ]);
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()->route('mfa.challenge');
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
        $audit->record($user, 'identity.login.succeeded', $request);

        if ($user->must_change_password) {
            return redirect()->route('password.change.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuthenticationAudit $audit): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $audit->record($user, 'identity.logout', $request);
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
