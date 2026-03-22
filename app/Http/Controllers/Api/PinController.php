<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Events\MessagePinned;
use App\Events\MessageUnpinned;
use App\Http\Controllers\Controller;
use App\Http\Resources\DirectMessageResource;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PinController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * List pinned messages in a channel.
     */
    public function index(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse();
        }

        $pinned = $channel->pinnedMessages()
            ->with(['user', 'reactions'])
            ->latest()
            ->get();

        return $this->successResponse(MessageResource::collection($pinned));
    }

    /**
     * Pin a channel message.
     */
    public function pin(Request $request, Channel $channel, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages)) {
            return $this->forbiddenResponse('You do not have permission to pin messages.');
        }

        if ($message->is_pinned) {
            return $this->successResponse(new MessageResource($message), 'Message is already pinned.');
        }

        $message->update(['is_pinned' => true]);

        broadcast(new MessagePinned($channel->id, $message->id, $user->id))->toOthers();

        return $this->successResponse(new MessageResource($message->load(['user', 'reactions'])), 'Message pinned.');
    }

    /**
     * Unpin a channel message.
     */
    public function unpin(Request $request, Channel $channel, Message $message): JsonResponse|Response
    {
        $user = $request->user();

        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages)) {
            return $this->forbiddenResponse('You do not have permission to unpin messages.');
        }

        if (! $message->is_pinned) {
            return $this->successResponse(null, 'Message is not pinned.');
        }

        $message->update(['is_pinned' => false]);

        broadcast(new MessageUnpinned($channel->id, $message->id))->toOthers();

        return $this->noContentResponse();
    }

    /**
     * List pinned messages in a DM group.
     */
    public function dmIndex(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $pinned = $dmGroup->messages()
            ->where('is_pinned', true)
            ->with(['user', 'reactions'])
            ->latest()
            ->get();

        return $this->successResponse(DirectMessageResource::collection($pinned));
    }

    /**
     * Pin a direct message.
     */
    public function dmPin(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if ($message->is_pinned) {
            return $this->successResponse(new DirectMessageResource($message), 'Message is already pinned.');
        }

        $message->update(['is_pinned' => true]);

        broadcast(new MessagePinned($dmGroup->id, $message->id, $user->id, true))->toOthers();

        return $this->successResponse(new DirectMessageResource($message->load(['user', 'reactions'])), 'Message pinned.');
    }

    /**
     * Unpin a direct message.
     */
    public function dmUnpin(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse|Response
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if (! $message->is_pinned) {
            return $this->successResponse(null, 'Message is not pinned.');
        }

        $message->update(['is_pinned' => false]);

        broadcast(new MessageUnpinned($dmGroup->id, $message->id, true))->toOthers();

        return $this->noContentResponse();
    }
}
