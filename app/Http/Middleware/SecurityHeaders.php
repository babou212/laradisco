<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->remove('X-Powered-By');

        // The strict lock-down suits JSON API responses, but would block the
        // Scribe docs page's own styles/scripts. Leave the docs UI unrestricted.
        if (! $request->is('docs', 'docs/*')) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }

        if (! app()->isLocal()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
