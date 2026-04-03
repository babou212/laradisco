<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteAccountRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\UploadAvatarRequest;
use App\Http\Resources\UserResource;
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
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));

        return (new UserResource($user))
            ->response();
    }

    /**
     * Delete the authenticated user's account.
     */
    public function destroy(DeleteAccountRequest $request): JsonResponse|Response
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

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

        return (new UserResource($user->refresh()))
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

        return $this->noContentResponse();
    }
}
