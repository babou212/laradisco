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

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))
            ->response();
    }

    /**
     * Update the authenticated user's profile.
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
