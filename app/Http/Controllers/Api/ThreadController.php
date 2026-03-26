<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateThreadReplyAction;
use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Events\ThreadMessageDeleted;
use App\Events\ThreadMessageEdited;
use App\Events\ThreadUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreThreadReplyRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ThreadResource;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Thread;
use App\Services\MentionService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThreadController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
        private readonly CreateThreadReplyAction $createThreadReplyAction,
    ) {}

    /**
     * Get thread details with parent message.
     */
    public function show(Request $request, Channel $channel, Thread $thread): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        if (! $this->permissionService->userCanViewChannel($request->user(), $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $thread->load([
            'user:id,username,name,nickname,avatar_path,status,custom_status',
            'parentMessage.user:id,username,name,nickname,avatar_path,status,custom_status',
            'latestReply.user:id,username,name,nickname,avatar_path,status,custom_status',
            'followers',
        ]);

        return $this->successResponse([
            'thread' => new ThreadResource($thread),
            'parent_message' => new MessageResource($thread->parentMessage),
        ]);
    }

    /**
     * Get cursor-paginated messages for a thread.
     */
    public function messages(Request $request, Channel $channel, Thread $thread): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        if (! $this->permissionService->userCanViewChannel($request->user(), $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $messages = $thread->messages()
            ->with([
                'user:id,username,name,nickname,avatar_path,status,custom_status',
                'attachments',
                'reactions',
            ])
            ->orderBy('created_at', 'asc')
            ->cursorPaginate(50)
            ->through(fn (Message $message) => new MessageResource($message));

        return $this->successResponse($messages);
    }

    /**
     * Post a reply to a message thread (creates thread if first reply).
     */
    public function storeReply(StoreThreadReplyRequest $request, Channel $channel, Message $message): JsonResponse
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if ($message->thread_id !== null) {
            return $this->errorResponse('Cannot start a thread from a thread reply.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->createThreadReplyAction->execute(
            $request->user(),
            $channel,
            $message,
            [
                'sender_device_id' => $request->validated('sender_device_id'),
                'history_ciphertext' => $request->validated('history_ciphertext'),
                'message_bytes' => $request->validated('message_bytes'),
                'epoch' => $request->validated('epoch', 0),
                'thread_name' => $request->validated('thread_name', 'Thread'),
                'mention_user_ids' => $request->validated('mention_user_ids', []),
                'mention_everyone' => $request->validated('mention_everyone', false),
                'mention_here' => $request->validated('mention_here', false),
            ],
        );

        if (! $result->success) {
            return $this->forbiddenResponse($result->error);
        }

        return $this->createdResponse(new MessageResource($result->reply), 'Reply sent successfully');
    }

    /**
     * Update a thread reply (owner only).
     */
    public function updateReply(UpdateChannelMessageRequest $request, Channel $channel, Thread $thread, Message $message): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        if ($message->thread_id !== $thread->id) {
            return $this->notFoundResponse('Message not found in this thread.');
        }

        if ($request->user()->id !== $message->user_id) {
            return $this->forbiddenResponse('You can only edit your own messages.');
        }

        $message->update([
            'sender_device_id' => $request->validated('sender_device_id', $message->sender_device_id),
            'history_ciphertext' => $request->validated('history_ciphertext', $message->history_ciphertext),
            'message_bytes' => $request->validated('message_bytes', $message->message_bytes),
            'epoch' => $request->validated('epoch', $message->epoch),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new ThreadMessageEdited($message))->toOthers();

        $thread->load('latestReply');
        if ($thread->latestReply && $thread->latestReply->id === $message->id) {
            broadcast(new ThreadUpdated($thread))->toOthers();
        }

        return $this->successResponse(new MessageResource($message), 'Reply updated successfully');
    }

    /**
     * Delete a thread reply (owner or permission-holder).
     */
    public function destroyReply(Request $request, Channel $channel, Thread $thread, Message $message): JsonResponse|Response
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        if ($message->thread_id !== $thread->id) {
            return $this->notFoundResponse('Message not found in this thread.');
        }

        $isOwner = $request->user()->id === $message->user_id;
        $canManage = $this->permissionService->userCanInChannel(
            $request->user(), $channel, PermissionFlag::ManageMessages
        );

        if (! $isOwner && ! $canManage) {
            return $this->forbiddenResponse('You do not have permission to delete this message.');
        }

        $messageId = $message->id;

        $message->update(['history_ciphertext' => null, 'message_bytes' => null]);
        $message->delete();

        $thread->decrement('message_count');

        $latestReply = $thread->messages()->latest()->first();
        $thread->update(['last_message_at' => $latestReply?->created_at]);

        broadcast(new ThreadMessageDeleted($messageId, $thread->id))->toOthers();

        $thread->refresh();
        broadcast(new ThreadUpdated($thread))->toOthers();

        return $this->noContentResponse();
    }

    /**
     * Follow a thread.
     */
    public function follow(Request $request, Channel $channel, Thread $thread): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        $thread->followers()->syncWithoutDetaching([$request->user()->id]);

        return $this->successResponse(null, 'Thread followed.');
    }

    /**
     * Unfollow a thread.
     */
    public function unfollow(Request $request, Channel $channel, Thread $thread): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        $thread->followers()->detach($request->user()->id);

        return $this->successResponse(null, 'Thread unfollowed.');
    }
}
