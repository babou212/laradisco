<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the generated API documentation to internal use only.
 *
 * The docs are always reachable in non-production environments. In production
 * they are hidden (404) unless `DOCS_ENABLED=true` is set, so the reference can
 * be opened deliberately on an internal/staging host without being public.
 */
class DocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production') || config('scribe.docs_enabled')) {
            return $next($request);
        }

        abort(404);
    }
}
