<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Concerns\AuthorizesGroupAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\ClaimGroupRequest;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\DirectMessageGroup;
use App\Models\MlsGroup;
use App\Models\MlsJoinRequest;
use App\Models\MlsMessage;
use App\Models\MlsWelcomeMessage;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MlsGroupController extends Controller
{
    use ApiResponse, AuthorizesGroupAccess;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Check the status of an MLS group.
     */
    public function status(Request $request, string $groupId): JsonResponse
    {
        $user = $request->user();

        $authError = $this->authorizeGroupAccess($user, $groupId);
        if ($authError) {
            return $authError;
        }

        $deviceId = $request->query('device_id')
            ?? $request->header('X-Device-Id');

        $existing = MlsGroup::where('group_id', $groupId)->first();

        if (! $existing) {
            return $this->successResponse([
                'exists' => false,
            ]);
        }

        $hasWelcome = false;
        if ($deviceId) {
            $hasWelcome = MlsWelcomeMessage::where('group_id', $groupId)
                ->where('recipient_user_id', $user->id)
                ->where('recipient_device_id', $deviceId)
                ->whereNull('consumed_at')
                ->exists();
        }

        $hasPendingJoinRequest = false;
        if ($deviceId) {
            $hasPendingJoinRequest = MlsJoinRequest::where('group_id', $groupId)
                ->where('device_id', $deviceId)
                ->where('status', 'pending')
                ->exists();
        }

        return $this->successResponse([
            'exists' => true,
            'creator_user_id' => $existing->creator_user_id,
            'is_own_group' => (int) $existing->creator_user_id === (int) $user->id,
            'claimed_at' => $existing->created_at?->toIso8601String(),
            'has_welcome' => $hasWelcome,
            'has_pending_join_request' => $hasPendingJoinRequest,
        ]);
    }

    /**
     * Claim ownership of an MLS group.
     */
    public function claim(ClaimGroupRequest $request, string $groupId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $authError = $this->authorizeGroupAccess($user, $groupId);
        if ($authError) {
            return $authError;
        }

        $deviceId = $validated['device_id']
            ?? $request->header('X-Device-Id');

        if (empty($deviceId)) {
            return $this->errorResponse('A device_id field or X-Device-Id header is required.', 422);
        }

        $force = $validated['force'] ?? false;

        try {
            MlsGroup::create([
                'group_id' => $groupId,
                'creator_user_id' => $user->id,
                'creator_device_id' => $deviceId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = MlsGroup::where('group_id', $groupId)->first();

            if ($existing && (int) $existing->creator_user_id === (int) $user->id) {
                $existing->update(['creator_device_id' => $deviceId]);

                MlsWelcomeMessage::where('group_id', $groupId)->delete();
                MlsMessage::where('group_id', $groupId)->delete();

                return $this->successResponse(['group_id' => $groupId, 'reclaimed' => true], 'Group reclaimed.');
            }

            if ($force && $existing) {
                $claimedAt = $existing->updated_at ?? $existing->created_at;
                $graceSeconds = 60;

                if ($claimedAt && $claimedAt->diffInSeconds(now()) < $graceSeconds) {
                    return response()->json([
                        'message' => 'Group was claimed recently. Wait for a welcome message from the group creator.',
                        'claimed_at' => $claimedAt->toIso8601String(),
                        'creator_user_id' => $existing->creator_user_id,
                        'retry_after' => $graceSeconds - $claimedAt->diffInSeconds(now()),
                    ], 409);
                }

                $existing->update([
                    'creator_user_id' => $user->id,
                    'creator_device_id' => $deviceId,
                ]);
                MlsWelcomeMessage::where('group_id', $groupId)->delete();
                MlsMessage::where('group_id', $groupId)->delete();

                return $this->successResponse(['group_id' => $groupId, 'reclaimed' => true], 'Group reclaimed.');
            }

            $hasWelcome = false;
            if ($existing) {
                $hasWelcome = MlsWelcomeMessage::where('group_id', $groupId)
                    ->where('recipient_user_id', $user->id)
                    ->where('recipient_device_id', $deviceId)
                    ->whereNull('consumed_at')
                    ->exists();
            }

            return response()->json([
                'message' => 'Group already claimed.',
                'claimed_at' => $existing?->created_at?->toIso8601String(),
                'creator_user_id' => $existing?->creator_user_id,
                'has_welcome' => $hasWelcome,
            ], 409);
        }

        return $this->createdResponse(['group_id' => $groupId], 'Group claimed.');
    }

    /**
     * List all MLS group IDs the authenticated user should be a member of.
     */
    public function userGroups(Request $request): JsonResponse
    {
        $user = $request->user();

        $cacheKey = CacheKeys::e2eeMlsGroups($user->id);
        $cached = Cache::tags([CacheKeys::userTag($user->id), CacheKeys::TAG_SIDEBAR])->get($cacheKey);
        if ($cached) {
            return $this->successResponse($cached);
        }

        $channels = $this->permissionService->getAccessibleChannels($user);
        $channelGroupIds = $channels->map(fn (Channel $ch) => 'channel:'.$ch->id)->all();

        $dmGroupIds = $user->directMessageGroups()->pluck('direct_message_groups.id')
            ->map(fn (int $id) => 'dm:'.$id)
            ->all();

        $result = array_merge($channelGroupIds, $dmGroupIds);

        Cache::tags([CacheKeys::userTag($user->id), CacheKeys::TAG_SIDEBAR])
            ->put($cacheKey, $result, CacheKeys::TTL_WARM);

        return $this->successResponse($result);
    }

    /**
     * Get member device bundles for a channel.
     */
    public function channelMemberBundles(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->permissionService->userCanViewChannel($request->user(), $channel)) {
            return $this->errorResponse('You do not have access to this channel.', 403);
        }

        $users = User::whereHas('devices', fn ($q) => $q->where('is_active', true))
            ->with([
                'roles',
                'devices' => fn ($q) => $q->where('is_active', true)->select('user_id', 'device_id'),
            ])
            ->get(['id']);

        $overrides = ChannelPermissionOverride::where('channel_id', $channel->id)->get();

        $users = $users->filter(fn (User $user) => $this->permissionService->userCanViewChannel($user, $channel, $overrides));

        $result = $users->map(fn ($user) => [
            'user_id' => $user->id,
            'devices' => $user->devices->map(fn ($d) => ['device_id' => $d->device_id])->values(),
        ])->values();

        return $this->successResponse($result);
    }

    /**
     * Get member device bundles for a DM group.
     */
    public function dmMemberBundles(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $users = $dmGroup->participants()
            ->whereHas('devices', fn ($q) => $q->where('is_active', true))
            ->with(['devices' => fn ($q) => $q->where('is_active', true)->select('user_id', 'device_id')])
            ->get(['users.id']);

        $result = $users->map(fn ($user) => [
            'user_id' => $user->id,
            'devices' => $user->devices->map(fn ($d) => ['device_id' => $d->device_id])->values(),
        ]);

        return $this->successResponse($result);
    }
}
