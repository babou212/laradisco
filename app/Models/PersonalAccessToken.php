<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Cache TTL in seconds for token lookups.
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Find the token instance matching the given token, with Redis caching.
     *
     * @param  string  $token
     * @return static|null
     */
    public static function findToken($token)
    {
        $hash = hash('sha256', $token);

        if (str_contains($token, '|')) {
            [$id, $plainToken] = explode('|', $token, 2);
            $hash = hash('sha256', $plainToken);
            $cacheKey = "sanctum:token:{$id}";
        } else {
            $cacheKey = "sanctum:token:hash:{$hash}";
        }

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            /** @var static $instance */
            $instance = (new self)->newFromBuilder($cached);

            // For ID-prefixed tokens, verify with timing-safe comparison
            if (isset($id) && ! hash_equals($instance->token, $hash)) {
                return null;
            }

            return $instance;
        }

        // Cache miss — query DB via parent logic
        $instance = parent::findToken($token);

        if ($instance) {
            Cache::put($cacheKey, $instance->getAttributes(), self::CACHE_TTL);
        }

        return $instance;
    }

    /**
     * Clear token cache when the token is deleted (logout/revoke).
     */
    public function delete()
    {
        Cache::forget("sanctum:token:{$this->id}");
        Cache::forget('sanctum:token:hash:'.$this->token);

        return parent::delete();
    }
}
