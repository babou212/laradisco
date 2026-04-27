<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateThreadReplyAction;
use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
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
use App\Services\ModerationAuditService;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ThreadController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly CreateThreadReplyAction $createThreadReplyAction,
        private readonly ModerationAuditService $auditService,
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

        $cacheKey = CacheKeys::threadDetails($thread->id);
        $cached = Cache::tags([CacheKeys::threadTag($thread->id), CacheKeys::channelTag($channel->id)])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $thread->load([
            'user:id,username,name,nickname,status,custom_status',
            'parentMessage.user:id,username,name,nickname,status,custom_status',
            'latestReply.user:id,username,name,nickname,status,custom_status',
            'followers',
        ]);

        $response = (new ThreadResource($thread))
            ->includePreviouslyLoadedRelationships()
            ->additional([
                'meta' => [
                    'parent_message' => (new MessageResource($thread->parentMessage))
                        ->includePreviouslyLoadedRelationships(),
                ],
            ])
            ->response();

        Cache::tags([CacheKeys::threadTag($thread->id), CacheKeys::channelTag($channel->id)])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_WARM);

        return $response;
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

        $cursor = $request->query('cursor');
        $includes = $request->query('include', '');
        $cacheKey = CacheKeys::threadMessages($thread->id).'.'.md5($includes);

        if (! $cursor) {
            $cached = Cache::tags([CacheKeys::threadMessagesTag($thread->id)])->get($cacheKey);
            if ($cached) {
                return response()->json($cached);
            }
        }

        $messages = QueryBuilder::for(
            $thread->messages()
        )
            ->allowedIncludes('user', 'reactions', 'encryptedAttachments')
            ->allowedSorts('created_at')
            ->defaultSort('created_at')
            ->cursorPaginate(50);

        $response = MessageResource::collection($messages)
            ->includePreviouslyLoadedRelationships()
            ->response();

        if (! $cursor) {
            Cache::tags([CacheKeys::threadTag($thread->id), CacheKeys::threadMessagesTag($thread->id)])
                ->put($cacheKey, $response->getData(true), CacheKeys::TTL_HOT);
        }

        return $response;
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
                'message_bytes' => $request->validated('message_bytes'),
                'epoch' => $request->validated('epoch', 0),
                'thread_name' => $request->validated('thread_name', 'Thread'),
                'mention_user_ids' => $request->validated('mention_user_ids', []),
                'mention_everyone' => $request->validated('mention_everyone', false),
                'mention_here' => $request->validated('mention_here', false),
                'client_temp_id' => $request->validated('client_temp_id'),
            ],
        );

        if (! $result->success) {
            return $this->forbiddenResponse($result->error);
        }

        return (new MessageResource($result->reply))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode($result->duplicate ? Response::HTTP_OK : Response::HTTP_CREATED);
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
            'message_bytes' => $request->validated('message_bytes', $message->message_bytes),
            'epoch' => $request->validated('epoch', $message->epoch),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        Cache::tags([CacheKeys::threadMessagesTag($thread->id)])->flush();

        broadcast(new ThreadMessageEdited($message))->toOthers();

        $thread->load('latestReply');
        if ($thread->latestReply && $thread->latestReply->id === $message->id) {
            broadcast(new ThreadUpdated($thread))->toOthers();
        }

        return (new MessageResource($message))
            ->response();
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
        $messageUserId = $message->user_id;

        $message->update(['history_ciphertext' => null, 'message_bytes' => null]);
        $message->delete();

        if (! $isOwner) {
            $this->auditService->log(
                actorId: $request->user()->id,
                action: ModerationAction::ThreadMessageDelete,
                targetUserId: $messageUserId,
                targetResourceId: $messageId,
                targetResourceType: 'thread_message',
                metadata: [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'thread_id' => $thread->id,
                ],
            );
        }

        $thread->decrement('message_count');

        $latestReply = $thread->messages()->latest()->first();
        $thread->update(['last_message_at' => $latestReply?->created_at]);

        Cache::tags([CacheKeys::threadMessagesTag($thread->id)])->flush();

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
