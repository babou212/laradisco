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
     * Pass the request through immediately, deferring tracking to terminate().
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Update last-seen timestamps after the response has been sent to the client.
     */
    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        $cacheKey = "user-last-seen-{$user->id}";

        if (! Cache::store('octane')->has($cacheKey)) {
            $user->update([
                'last_seen_at' => now(),
            ]);

            Cache::store('octane')->put($cacheKey, true, now()->addMinutes(5));
        }

        $deviceId = $request->header('X-Device-Id');
        if ($deviceId && Str::isUuid($deviceId)) {
            $deviceCacheKey = "device-last-seen-{$user->id}-{$deviceId}";

            if (! Cache::store('octane')->has($deviceCacheKey)) {
                $updated = UserDevice::where('user_id', $user->id)
                    ->where('device_id', $deviceId)
                    ->where('is_active', true)
                    ->update(['last_seen_at' => now()]);

                if ($updated > 0) {
                    Cache::store('octane')->put($deviceCacheKey, true, now()->addMinutes(5));
                }
            }
        }
    }
}
