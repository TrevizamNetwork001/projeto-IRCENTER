<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $nonce = base64_encode(random_bytes(18));

        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $policy = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "frame-src 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self' https://viacep.com.br",
            "script-src 'self' 'nonce-{$nonce}'",
            "script-src-attr 'none'",
            "style-src 'self' 'nonce-{$nonce}'",
            "style-src-attr 'none'",
            "media-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
        ]).';';

        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            $policy
        );

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->headers->set(
            'X-Frame-Options',
            'SAMEORIGIN'
        );

        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        $response->headers->set(
            'Cross-Origin-Opener-Policy',
            'same-origin'
        );

        $response->headers->set(
            'X-Permitted-Cross-Domain-Policies',
            'none'
        );

        return $response;
    }
}
