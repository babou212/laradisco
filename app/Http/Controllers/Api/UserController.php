<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Show a specific user's public profile.
     */
    public function show(User $user): JsonResponse
    {
        $cacheKey = CacheKeys::userProfile($user->id);
        $cached = Cache::tags([CacheKeys::userTag($user->id)])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $user->load(['roles' => fn ($query) => $query->orderByDesc('position')]);

        $response = (new UserResource($user))
            ->includePreviouslyLoadedRelationships()
            ->response();

        Cache::tags([CacheKeys::userTag($user->id)])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_COLD);

        return $response;
    }
}
