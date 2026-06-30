<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Events\UserActivityUpdated;
use App\Events\UserDeleted;
use App\Events\UserProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteAccountRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Services\PresenceService;
use App\Services\UserDeletionService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Settings: Profile
 */
class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's profile.
     *
     * @response 200 {"data":{"type":"users","id":"7","attributes":{"username":"alice","display_name":"Alice","avatar_urls":null,"status":"online","custom_status":null,"email":"alice@example.com","created_at":"2026-06-30T12:00:00.000000Z"}}}
     */
    public function show(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))
            ->response();
    }

    /**
     * Update the authenticated user's profile.
     *
     * @response 200 {"data":{"type":"users","id":"7","attributes":{"username":"alice","display_name":"Alice Cooper","avatar_urls":null,"status":"online","custom_status":"coding","email":"alice@example.com","created_at":"2026-06-30T12:00:00.000000Z"}}}
     */
    public function update(UpdateProfileRequest $request, PresenceService $presenceService): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $activityDisabled = $user->isDirty('show_activity') && ! $user->show_activity;

        $user->save();

        // Disabling activity sharing must immediately clear any live activity
        // from the presence registry so other clients stop seeing it.
        if ($activityDisabled) {
            $presenceService->updateActivity($user, null);
            UserActivityUpdated::dispatch($user, null);
        }

        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));

        UserProfileUpdated::dispatch($user);

        return (new UserResource($user))
            ->response();
    }

    /**
     * Delete the authenticated user's account.
     *
     * @response 204
     */
    public function destroy(DeleteAccountRequest $request, UserDeletionService $userDeletionService): JsonResponse|Response
    {
        $user = $request->user();

        $userId = $user->id;
        $username = $user->username;

        $userDeletionService->delete($user);

        UserDeleted::dispatch($userId, $username);

        return $this->noContentResponse();
    }

    /**
     * Upload or replace the authenticated user's avatar.
     *
     * @response 200 {"data":{"type":"users","id":"7","attributes":{"username":"alice","display_name":"Alice","avatar_urls":{"original":"https://cdn.example.com/avatars/7.png"},"status":"online","custom_status":null,"created_at":"2026-06-30T12:00:00.000000Z"}}}
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->addMediaFromRequest('avatar')
            ->toMediaCollection('avatar');

        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));

        $fresh = $user->refresh();

        UserProfileUpdated::dispatch($fresh);

        return (new UserResource($fresh))
            ->response();
    }

    /**
     * Delete the authenticated user's avatar.
     *
     * @response 204
     */
    public function deleteAvatar(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        $user->clearMediaCollection('avatar');

        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));

        UserProfileUpdated::dispatch($user->refresh());

        return $this->noContentResponse();
    }
}
