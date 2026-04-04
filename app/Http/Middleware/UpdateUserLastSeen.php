<?php

namespace App\Http\Middleware;

use App\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

            $deviceId = $request->header('X-Device-Id');
            if ($deviceId && Str::isUuid($deviceId)) {
                $deviceCacheKey = "device-last-seen-{$request->user()->id}-{$deviceId}";

                if (! Cache::has($deviceCacheKey)) {
                    $updated = UserDevice::where('user_id', $request->user()->id)
                        ->where('device_id', $deviceId)
                        ->where('is_active', true)
                        ->update(['last_seen_at' => now()]);

                    if ($updated > 0) {
                        Cache::put($deviceCacheKey, true, now()->addMinutes(5));
                    }
                }
            }
        }

        return $next($request);
    }
}
