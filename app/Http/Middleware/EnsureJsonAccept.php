<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content negotiation middleware.
 *
 * Ensures that API clients include an Accept header that accepts JSON.
 * Requests that explicitly refuse JSON (e.g. Accept: text/html without
 * application/json) receive a 406 Not Acceptable response.
 */
class EnsureJsonAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        $accept = $request->header('Accept', '*/*');

        if (str_contains($accept, '*/*') || str_contains($accept, 'application/*')) {
            return $next($request);
        }

        if (str_contains($accept, 'application/json')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Not Acceptable. This API only serves application/json responses. Set Accept: application/json.',
        ], Response::HTTP_NOT_ACCEPTABLE);
    }
}
