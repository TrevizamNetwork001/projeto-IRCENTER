<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UploadProfilePhotoRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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

        $avatarKey = $request->validated('avatar_key');

        $user->update([
            'avatar_key' => $avatarKey,
            'avatar_mode' => $avatarKey === null
                ? User::AVATAR_MODE_INITIALS
                : User::AVATAR_MODE_AVATAR,
        ]);

        $audit->record(
            'avatar_updated',
            $user,
            [
                'avatar_key' => $oldValues['avatar_key'] ?? null,
                'avatar_mode' => $oldValues['avatar_mode'] ?? null,
            ],
            [
                'avatar_key' => $user->avatar_key,
                'avatar_mode' => $user->avatar_mode,
            ],
            $user->email
        );

        return back()->with(
            'success',
            'Identidade visual atualizada com sucesso.'
        );
    }

    public function uploadPhoto(
        UploadProfilePhotoRequest $request,
        AuditService $audit
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $previousPath = $user->avatar_photo_path;

        $path = $request->file('photo')->store(
            'profile-photos',
            'public'
        );

        $user->update([
            'avatar_photo_path' => $path,
            'avatar_mode' => User::AVATAR_MODE_PHOTO,
        ]);

        if ($previousPath !== null) {
            Storage::disk('public')->delete($previousPath);
        }

        $audit->record(
            'photo_updated',
            $user,
            null,
            ['avatar_mode' => $user->avatar_mode],
            $user->email
        );

        return back()->with(
            'success',
            'Foto de perfil atualizada com sucesso.'
        );
    }

    public function removePhoto(AuditService $audit): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->avatar_photo_path === null) {
            return back();
        }

        Storage::disk('public')->delete($user->avatar_photo_path);

        $user->update([
            'avatar_photo_path' => null,
            'avatar_mode' => $user->avatar_key !== null
                ? User::AVATAR_MODE_AVATAR
                : User::AVATAR_MODE_INITIALS,
        ]);

        $audit->record(
            'photo_removed',
            $user,
            null,
            ['avatar_mode' => $user->avatar_mode],
            $user->email
        );

        return back()->with(
            'success',
            'Foto de perfil removida com sucesso.'
        );
    }
}
