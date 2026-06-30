<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @group Channels & Messages
 */
class ChannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Show a channel
     *
     * Get a single channel's details including the user's resolved permissions
     * for it (`channelPermissions`).
     *
     * @response 200 {"data": {"type": "channels", "id": "42", "attributes": {"name": "general", "topic": "Company-wide chatter", "channel_type": "text", "is_private": false, "category_id": 3, "position": 0, "slowmode_seconds": 0, "channelPermissions": {"canSendMessages": true, "canManageMessages": false, "canPinMessages": false, "canAddReactions": true, "canAttachFiles": true, "canMentionEveryone": false}, "has_unread": false, "created_at": "2026-06-30T12:00:00.000000Z"}}}
     */
    public function show(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $cacheKey = CacheKeys::channelDetails($user->id, $channel->id);
        $tags = [CacheKeys::channelTag($channel->id), CacheKeys::userTag($user->id)];

        $data = Cache::tags($tags)->remember($cacheKey, CacheKeys::TTL_COLD, function () use ($user, $channel) {
            $channel->channelPermissions = [
                'canSendMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::SendMessages),
                'canManageMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::ManageMessages),
                'canPinMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages),
                'canAddReactions' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::AddReactions),
                'canAttachFiles' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::AttachFiles),
                'canMentionEveryone' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::MentionEveryone),
            ];

            return (new ChannelResource($channel))->response()->getData(true);
        });

        return response()->json($data);
    }
}
