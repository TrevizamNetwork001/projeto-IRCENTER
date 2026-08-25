<?php

namespace App\Http\Middleware;

use App\Services\Identity\RecentPasswordConfirmation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(RecentPasswordConfirmation::class)->isValid(
            $request->session(),
            timeout: (int) config('identity.password_confirmation_timeout', 900),
        )) {
            return redirect()->route('profile.security')
                ->with('warning', 'Confirme sua senha para continuar.');
        }

        return $next($request);
    }
}
