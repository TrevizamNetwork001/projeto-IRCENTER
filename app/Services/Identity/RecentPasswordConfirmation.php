<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;

class RecentPasswordConfirmation
{
    public const SESSION_KEY = 'auth.password_confirmed_at';

    public function confirm(User $user, Session $session, string $password): bool
    {
        if (! Hash::check($password, $user->password)) {
            return false;
        }

        $session->put(self::SESSION_KEY, time());

        return true;
    }

    public function isValid(Session $session, ?int $now = null, ?int $timeout = null): bool
    {
        $confirmedAt = $session->get(self::SESSION_KEY);
        $timeout ??= (int) config('auth.password_timeout', 10800);

        return is_int($confirmedAt)
            && (($now ?? time()) - $confirmedAt) < $timeout;
    }
}
