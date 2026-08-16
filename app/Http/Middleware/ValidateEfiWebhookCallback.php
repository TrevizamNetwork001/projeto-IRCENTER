<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateEfiWebhookCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = trim((string) config(
            'finance_fiscal.providers.efi.webhook_callback_secret',
            ''
        ));

        if (preg_match('/^[a-f0-9]{64}$/', $configured) !== 1) {
            abort(404);
        }

        $supplied = $request->route('callbackSecret');

        /*
         * Compatibilidade de implantação: a rota sem segredo só deve existir
         * durante a troca controlada da URL cadastrada na Efí.
         */
        if ($supplied === null) {
            if (! config(
                'finance_fiscal.providers.efi.webhook_legacy_route_enabled',
                false
            )) {
                abort(404);
            }

            return $next($request);
        }

        if (
            ! is_string($supplied)
            || preg_match('/^[a-f0-9]{64}$/', $supplied) !== 1
            || ! hash_equals($configured, $supplied)
        ) {
            abort(404);
        }

        $request->route()?->forgetParameter('callbackSecret');

        return $next($request);
    }
}
