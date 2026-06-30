<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Users
 */
class UserController extends Controller
{
    use ApiResponse;

    /**
     * Show a user profile
     *
     * Show a specific user's public profile. The `email`, `permissions` and
     * other private fields are only present when requesting your own profile.
     *
     * @response 200 {"data": {"type": "users", "id": "8", "attributes": {"username": "alice", "display_name": "Alice", "avatar_urls": null, "status": "online", "custom_status": null, "created_at": "2026-06-01T09:00:00.000000Z"}}}
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

    /**
     * Stream a user avatar
     *
     * Stream a user's avatar (a given conversion) from the private media disk.
     * Responds with the raw image bytes (not JSON); `{size}` is one of `thumb`,
     * `small`, `medium`, or `original`, and `{version}` is an opaque
     * cache-busting token.
     *
     * Avatars are not security-sensitive, so unlike other media they are served
     * through this stable, authenticated endpoint instead of a short-lived
     * presigned URL — the URL never expires, so clients (and any snapshot of it
     * in presence/notifications) keep resolving. The {version} segment is an
     * opaque cache-busting token (the media id); we always stream the user's
     * current avatar so frozen snapshot URLs survive an avatar change.
     */
    public function avatar(User $user, string $version, string $size): StreamedResponse
    {
        $media = $user->getFirstMedia('avatar');
        abort_if($media === null, 404);

        $conversion = $size === 'original' ? '' : $size;
        abort_if($conversion !== '' && ! $media->hasGeneratedConversion($conversion), 404);

        return response()->stream(function () use ($media, $conversion) {
            $stream = $media->stream($conversion);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=2592000, immutable',
            'ETag' => '"'.$media->id.'-'.$size.'"',
        ]);
    }
}
