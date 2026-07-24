<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => auth()->user(),
            'avatars' => User::avatars(),
        ]);
    }

    public function updateAvatar(
        UpdateAvatarRequest $request,
        AuditService $audit
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $oldValues = $user->getOriginal();

        $user->update([
            'avatar_key' => $request->validated('avatar_key'),
        ]);

        $audit->record(
            'avatar_updated',
            $user,
            [
                'avatar_key' => $oldValues['avatar_key'] ?? null,
            ],
            [
                'avatar_key' => $user->avatar_key,
            ],
            $user->email
        );

        return back()->with(
            'success',
            'Avatar atualizado com sucesso.'
        );
    }
}
