<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Events\ThreadMessageDeleted;
use App\Events\ThreadMessageEdited;
use App\Events\ThreadMessageSent;
use App\Events\ThreadUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreThreadReplyRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ThreadResource;
use App\Models\Channel;
use App\Models\EncryptedSearchToken;
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
            ->cursorPaginate(50);

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

        if (! $this->permissionService->userCanInChannel($request->user(), $channel, PermissionFlag::SendMessages)) {
            return $this->forbiddenResponse('You do not have permission to send messages in this channel.');
        }

        if ($message->thread_id !== null) {
            return $this->errorResponse('Cannot start a thread from a thread reply.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();

        $thread = $message->threadStarted;

        if (! $thread) {
            $threadName = mb_substr(strip_tags($message->content), 0, 50);

            $thread = Thread::create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'message_id' => $message->id,
                'name' => $threadName,
                'message_count' => 0,
                'last_message_at' => now(),
            ]);

            $followerIds = array_unique([$user->id, $message->user_id]);
            $thread->followers()->attach($followerIds);
        }

        if ($thread->is_locked) {
            return $this->forbiddenResponse('This thread is locked.');
        }

        $reply = $channel->messages()->create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'content' => $request->validated('content'),
            'is_encrypted' => true,
            'sender_device_id' => $request->validated('sender_device_id'),
        ]);

        $reply->load(['user:id,username,name,nickname,avatar_path,status,custom_status', 'attachments']);

        $thread->increment('message_count');
        $thread->update(['last_message_at' => now()]);

        if (! $thread->followers()->where('user_id', $user->id)->exists()) {
            $thread->followers()->attach($user->id);
        }

        $this->mentionService->processMentionsFromMetadata(
            $reply,
            $request->validated('mention_user_ids', []),
            $request->validated('mention_everyone', false),
            $request->validated('mention_here', false),
        );

        $searchTokens = $request->validated('search_tokens', []);
        if (! empty($searchTokens)) {
            EncryptedSearchToken::insertTokensForMessage('channel', $channel->id, $reply->id, $searchTokens);
        }

        broadcast(new ThreadMessageSent($reply))->toOthers();

        $thread->refresh();
        broadcast(new ThreadUpdated($thread))->toOthers();

        return $this->createdResponse(new MessageResource($reply), 'Reply sent successfully');
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
            'content' => $request->validated('content'),
            'is_encrypted' => true,
            'sender_device_id' => $request->validated('sender_device_id', $message->sender_device_id),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        $searchTokens = $request->validated('search_tokens', null);
        if ($searchTokens !== null) {
            EncryptedSearchToken::replaceTokensForMessage('channel', $channel->id, $message->id, $searchTokens);
        }

        broadcast(new ThreadMessageEdited($message))->toOthers();

        // Update thread preview if this was the latest reply
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

        EncryptedSearchToken::deleteTokensForMessage('channel', $channel->id, $messageId);

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
