<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDocumentationApiClient
{
    public function __construct(
        private readonly ThrottleRequests $throttle
    ) {}

    public function handle(
        $request,
        Closure $next,
        $maxAttempts = 'documentation-api-client',
        $decayMinutes = 1,
        $prefix = ''
    ): Response {
        return $this->throttle->handle(
            $request,
            $next,
            'documentation-api-client'
        );
    }
}
