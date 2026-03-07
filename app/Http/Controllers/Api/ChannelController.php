<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Get a single channel's details including the user's permissions.
     */
    public function show(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $channel->channelPermissions = [
            'canSendMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::SendMessages),
            'canManageMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::ManageMessages),
            'canPinMessages' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages),
            'canAddReactions' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::AddReactions),
            'canAttachFiles' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::AttachFiles),
            'canMentionEveryone' => $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::MentionEveryone),
        ];

        return $this->successResponse(new ChannelResource($channel));
    }
}
