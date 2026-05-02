<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $cacheKey = "user-last-seen-{$request->user()->id}";

            if (! Cache::has($cacheKey)) {
                $request->user()->update([
                    'last_seen_at' => now(),
                ]);

                Cache::put($cacheKey, true, now()->addMinutes(5));
            }
        }

        return $next($request);
    }
}
