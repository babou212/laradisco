<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency key middleware for POST mutations.
 *
 * When a client sends an `Idempotency-Key` header on a POST request,
 * the middleware caches the response for that key. Subsequent requests
 * with the same key return the cached response, preventing duplicate
 * resource creation (e.g. duplicate messages, duplicate invite links).
 */
class IdempotencyKey
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $next($request);
        }

        $userId = $request->user()?->id ?? 'anonymous';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return response(
                $cached['body'],
                $cached['status'],
                $cached['headers'],
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => array_map(
                    fn ($values) => $values[0] ?? '',
                    $response->headers->all(),
                ),
            ], self::CACHE_TTL_SECONDS);
        }

        return $response;
    }
}
