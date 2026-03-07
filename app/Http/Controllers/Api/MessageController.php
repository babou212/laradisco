<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CursorPaginateRequest;
use App\Http\Requests\Api\StoreChannelMessageRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\Message;
use App\Services\MentionService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Get cursor-paginated messages for a channel.
     */
    public function index(CursorPaginateRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $messages = $channel->messages()
            ->with(['user', 'attachments', 'reactions', 'replyTo.user'])
            ->orderBy('created_at', 'asc')
            ->cursorPaginate(50);

        return $this->successResponse($messages);
    }

    /**
     * Store a new channel message.
     */
    public function store(StoreChannelMessageRequest $request, Channel $channel): JsonResponse
    {
        $message = $channel->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'reply_to_id' => $request->validated('reply_to_id'),
        ]);

        $message->load(['user', 'replyTo.user']);

        broadcast(new MessageSent($message))->toOthers();

        $this->mentionService->processMentions($message);

        return $this->createdResponse(
            new MessageResource($message),
            'Created successfully',
            route('api.channels.messages.store', $channel).'/'.$message->id,
        );
    }

    /**
     * Update a channel message (owner only).
     */
    public function update(UpdateChannelMessageRequest $request, Channel $channel, Message $message): JsonResponse
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if ($request->user()->id !== $message->user_id) {
            return $this->forbiddenResponse('You can only edit your own messages.');
        }

        $message->update([
            'content' => $request->validated('content'),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new MessageEdited($message))->toOthers();

        return $this->successResponse(
            new MessageResource($message),
            'Message updated successfully',
        );
    }

    /**
     * Delete a channel message (owner or permission-holder).
     */
    public function destroy(Request $request, Channel $channel, Message $message): JsonResponse|Response
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        $isOwner = $request->user()->id === $message->user_id;
        $canManage = $this->permissionService->userCanInChannel(
            $request->user(), $channel, PermissionFlag::ManageMessages
        );

        if (! $isOwner && ! $canManage) {
            return $this->forbiddenResponse('You do not have permission to delete this message.');
        }

        $messageId = $message->id;
        $message->delete();

        broadcast(new MessageDeleted($messageId, $channel->id))->toOthers();

        return $this->noContentResponse();
    }
}
