<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\UserMfaCredential;
use App\Services\Identity\MfaManager;
use App\Services\Identity\RecentPasswordConfirmation;
use App\Services\Identity\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;

class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $credential = UserMfaCredential::query()->where('user_id', $request->user()->id)->first();

        return view('profile.security', [
            'mfaEnabled' => $credential?->confirmed_at !== null,
            'recoveryCodes' => $request->session()->pull('identity.recovery_codes'),
        ]);
    }

    public function confirmPassword(Request $request, RecentPasswordConfirmation $confirmation): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        if (! $confirmation->confirm($request->user(), $request->session(), $validated['password'])) {
            return back()->withErrors(['password' => 'A senha atual está incorreta.']);
        }

        return redirect()->route('profile.security')->with('status', 'Senha confirmada.');
    }

    public function beginEnrollment(Request $request, MfaManager $manager): View
    {
        return view('profile.mfa-enrollment', ['enrollment' => $manager->beginEnrollment($request->user())]);
    }

    public function confirmEnrollment(Request $request, MfaManager $manager): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        try {
            $codes = $manager->confirmEnrollment($request->user(), $validated['code']);
        } catch (LogicException) {
            throw ValidationException::withMessages(['code' => 'Código MFA inválido ou expirado.']);
        }

        return redirect()->route('profile.security')
            ->with('identity.recovery_codes', $codes)
            ->with('status', 'MFA ativado. Guarde os códigos de recuperação agora.');
    }

    public function regenerateRecoveryCodes(Request $request, MfaManager $manager): RedirectResponse
    {
        $codes = $manager->regenerateRecoveryCodes($request->user(), true);

        return redirect()->route('profile.security')->with('identity.recovery_codes', $codes);
    }

    public function disable(Request $request, MfaManager $manager): RedirectResponse
    {
        $manager->disable($request->user(), true);

        return redirect()->route('profile.security')->with('status', 'MFA desativado.');
    }

    public function revokeOtherSessions(Request $request, SessionRevoker $revoker): RedirectResponse
    {
        $revoker->revokeOthers($request->user(), $request->session()->getId());

        return redirect()->route('profile.security')->with('status', 'Outras sessões encerradas.');
    }
}
