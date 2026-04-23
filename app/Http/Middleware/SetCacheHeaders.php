<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add ETag and Last-Modified headers to cacheable GET responses.
 *
 * ETag is computed from a SHA-256 hash of the response body.
 * If the client sends a matching If-None-Match header, a 304 Not Modified
 * response is returned with no body, saving bandwidth.
 */
class SetCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            ! $request->isMethod('GET') ||
            ! $response instanceof JsonResponse ||
            $response->getStatusCode() !== 200
        ) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false) {
            return $response;
        }
        $etag = '"'.hash('xxh128', $content).'"';

        $response->header('ETag', $etag);
        $response->header('Cache-Control', 'private, no-cache');

        $ifNoneMatch = $request->header('If-None-Match');

        if ($ifNoneMatch !== null && $this->etagMatches($ifNoneMatch, $etag)) {
            return response()->noContent(304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'private, no-cache',
            ]);
        }

        return $response;
    }

    /**
     * Check if the client's If-None-Match header matches the current ETag.
     */
    private function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === '*') {
            return true;
        }

        $clientTags = array_map('trim', explode(',', $ifNoneMatch));

        return in_array($etag, $clientTags, strict: true);
    }
}
