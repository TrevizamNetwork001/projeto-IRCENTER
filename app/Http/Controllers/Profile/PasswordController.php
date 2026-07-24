<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('profile.password');
    }

    public function update(
        ChangePasswordRequest $request,
        AuditService $audit
    ): RedirectResponse {
        $user = $request->user();
        $oldValues = $user->getOriginal();

        $user->forceFill([
            'password' => Hash::make(
                $request->string('password')->toString()
            ),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => null,
        ])->save();

        $request->session()->regenerate();

        $audit->record(
            'password_changed',
            $user,
            [
                'password_changed_at' =>
                    $oldValues['password_changed_at'] ?? null,
            ],
            [
                'password_changed_at' =>
                    $user->password_changed_at?->toISOString(),
            ],
            $user->email,
            $request
        );

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Senha alterada com sucesso.');
    }
}
