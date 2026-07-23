<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordWasChanged
{
    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {
        $user = $request->user();

        if (
            $user === null
            || ! $user->must_change_password
            || $request->routeIs([
                'password.change.edit',
                'password.change.update',
                'logout',
            ])
        ) {
            return $next($request);
        }

        return redirect()
            ->route('password.change.edit')
            ->with(
                'warning',
                'Defina uma nova senha para continuar.'
            );
    }
}
