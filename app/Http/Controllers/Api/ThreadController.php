<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateThreadReplyAction;
use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Enums\PermissionFlag;
use App\Events\ThreadDeleted;
use App\Events\ThreadMessageDeleted;
use App\Events\ThreadMessageEdited;
use App\Events\ThreadUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MessagePaginateRequest;
use App\Http\Requests\Api\StoreThreadReplyRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ThreadResource;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Thread;
use App\Services\MessageWindowService;
use App\Services\ModerationAuditService;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Threads
 */
class ThreadController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly CreateThreadReplyAction $createThreadReplyAction,
        private readonly ModerationAuditService $auditService,
        private readonly MessageWindowService $windowService,
    ) {}

    /**
     * Show a thread
     *
     * Get thread details. `meta.parent_message` carries the message the thread
     * was started from.
     *
     * @response 200 {"data": {"type": "threads", "id": "5", "attributes": {"channel_id": 42, "message_id": 1858, "name": "Thread", "message_count": 3, "last_message_at": "2026-06-30T12:20:00.000000Z", "is_archived": false, "is_locked": false, "created_at": "2026-06-30T12:00:00.000000Z"}, "relationships": {"user": {"data": {"type": "users", "id": "7"}}, "latestReply": {"data": {"type": "messages", "id": "1861"}}}}, "meta": {"parent_message": {"data": {"type": "messages", "id": "1858"}}}}
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
            'user:id,username,status,custom_status',
            'parentMessage.user:id,username,status,custom_status',
            'latestReply.user:id,username,status,custom_status',
            'followers',
        ]);

        $response = (new ThreadResource($thread))
            ->includePreviouslyLoadedRelationships()
            ->additional([
                'meta' => [
                    'parent_message' => (new MessageResource($thread->parentMessage))
                        ->includePreviouslyLoadedRelationships()
                        ->response($request)
                        ->getData(true),
                ],
            ])
            ->response();

        Cache::tags([CacheKeys::threadTag($thread->id), CacheKeys::channelTag($channel->id)])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_WARM);

        return $response;
    }

    /**
     * List thread messages
     *
     * Returns replies oldest→newest: latest 50 by default, or anchored on
     * `before`/`after`/`around` message ids.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: user, reactions, attachments. Example: user,reactions
     * @queryParam limit integer Page size, defaults to 50. Example: 50
     * @queryParam before integer Return replies older than this message id. Example: 1861
     * @queryParam after integer Return replies newer than this message id. Example: 1859
     * @queryParam around integer Return a window centred on this message id. Example: 1860
     *
     * @response 200 {"data": [{"type": "messages", "id": "1860", "attributes": {"channel_id": 42, "user_id": 7, "content": "a reply", "is_pinned": false, "is_edited": false, "reply_to_id": null, "thread_id": 5, "created_at": "2026-06-30T12:10:00.000000Z"}}], "meta": {"has_more_before": false, "has_more_after": false, "oldest_id": "1860", "newest_id": "1860"}}
     */
    public function messages(MessagePaginateRequest $request, Channel $channel, Thread $thread): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        if (! $this->permissionService->userCanViewChannel($request->user(), $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $allowedIncludes = ['user', 'reactions', 'attachments'];

        $limit = (int) $request->validated('limit', 50);

        if ($around = $request->validated('around')) {
            $target = $thread->messages()
                ->where('id', $around)
                ->first();

            if (! $target) {
                return $this->notFoundResponse('Target message not found in this thread.');
            }

            /** @var Builder<Model> $windowQuery */
            $windowQuery = QueryBuilder::for($thread->messages())
                ->allowedIncludes(...$allowedIncludes)
                ->getEloquentBuilder();

            $window = $this->windowService->windowAround($windowQuery, $target);

            return $this->paginatedResponse(
                $window['items'],
                $window['hasMoreBefore'],
                $window['hasMoreAfter'],
                $window['oldestId'],
                $window['newestId'],
            );
        }

        $before = $request->validated('before');
        $after = $request->validated('after');

        $base = QueryBuilder::for($thread->messages())
            ->allowedIncludes(...$allowedIncludes)
            ->getEloquentBuilder();

        $includes = (string) $request->query('include', '');
        $isLatestNoArg = $before === null && $after === null;
        $cacheKey = CacheKeys::threadMessages($thread->id).'.latest.l'.$limit.'.'.md5($includes);

        if ($isLatestNoArg) {
            $cached = Cache::tags([CacheKeys::threadMessagesTag($thread->id)])->get($cacheKey);
            if ($cached) {
                return response()->json($cached);
            }
        }

        if ($after !== null) {
            $rows = (clone $base)
                ->where('id', '>', (int) $after)
                ->orderBy('id', 'asc')
                ->limit($limit + 1)
                ->get();

            $hasMoreAfter = $rows->count() > $limit;
            $items = $rows->take($limit)->values();
            $hasMoreBefore = true;
        } else {
            $q = (clone $base)->orderBy('id', 'desc')->limit($limit + 1);
            if ($before !== null) {
                $q->where('id', '<', (int) $before);
            }
            $rows = $q->get();

            $hasMoreBefore = $rows->count() > $limit;
            $items = $rows->take($limit)->reverse()->values();
            $hasMoreAfter = $before !== null;
        }

        $oldestId = $items->isNotEmpty() ? (string) $items->first()->getKey() : null;
        $newestId = $items->isNotEmpty() ? (string) $items->last()->getKey() : null;

        $response = $this->paginatedResponse(
            $items,
            $hasMoreBefore,
            $hasMoreAfter,
            $oldestId,
            $newestId,
        );

        if ($isLatestNoArg) {
            Cache::tags([CacheKeys::threadTag($thread->id), CacheKeys::threadMessagesTag($thread->id)])
                ->put($cacheKey, $response->getData(true), CacheKeys::TTL_HOT);
        }

        return $response;
    }

    /**
     * @param  Collection<int, Model>  $items
     */
    private function paginatedResponse(
        Collection $items,
        bool $hasMoreBefore,
        bool $hasMoreAfter,
        ?string $oldestId,
        ?string $newestId,
    ): JsonResponse {
        $response = MessageResource::collection($items)
            ->includePreviouslyLoadedRelationships()
            ->response();

        $payload = $response->getData(true);
        $payload['meta'] = array_merge($payload['meta'] ?? [], [
            'has_more_before' => $hasMoreBefore,
            'has_more_after' => $hasMoreAfter,
            'oldest_id' => $oldestId,
            'newest_id' => $newestId,
        ]);
        $response->setData($payload);

        return $response;
    }

    /**
     * Post a thread reply
     *
     * Post a reply to a message thread, creating the thread if this is the first
     * reply. Returns 201 for a new reply, or 200 when an idempotent retry matches
     * an existing one.
     *
     * @response 201 {"data": {"type": "messages", "id": "1861", "attributes": {"channel_id": 42, "user_id": 7, "content": "first reply", "is_pinned": false, "is_edited": false, "reply_to_id": null, "thread_id": 5, "created_at": "2026-06-30T12:15:00.000000Z"}}}
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
                'content' => $request->validated('content'),
                'attachment_ids' => $request->validated('attachment_ids', []),
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
     * Update a thread reply
     *
     * Edit a thread reply. Owner only. Marks the message as edited.
     *
     * @response 200 {"data": {"type": "messages", "id": "1861", "attributes": {"channel_id": 42, "user_id": 7, "content": "edited reply", "is_edited": true, "edited_at": "2026-06-30T12:18:00.000000Z", "thread_id": 5, "created_at": "2026-06-30T12:15:00.000000Z"}}}
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
            'content' => $request->validated('content', $message->content),
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
     * Delete a thread reply
     *
     * Delete a thread reply. Allowed for the owner or a permission-holder
     * (moderator). Deleting the last reply removes the now-empty thread.
     *
     * @response 204
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

        $message->update(['content' => null]);
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

        // Determine the live reply count rather than trusting message_count,
        // which is a raw decrement and can drift.
        $remainingReplies = $thread->messages()->count();

        Cache::tags([CacheKeys::threadMessagesTag($thread->id)])->flush();

        broadcast(new ThreadMessageDeleted($messageId, $thread->id))->toOthers();

        // The thread's last reply was just removed: delete the now-empty thread
        // so it no longer renders a preview on its parent message. The DB cascade
        // cleans up followers and the (trashed) reply rows; the parent message
        // (thread_id NULL) is untouched.
        if ($remainingReplies === 0) {
            $parentMessageId = $thread->message_id;
            $threadId = $thread->id;

            Cache::tags([CacheKeys::threadTag($thread->id), CacheKeys::channelTag($channel->id)])->flush();

            $thread->delete();

            broadcast(new ThreadDeleted($parentMessageId, $threadId, $channel->id))->toOthers();

            return $this->noContentResponse();
        }

        $latestReply = $thread->messages()->latest()->first();
        $thread->update(['last_message_at' => $latestReply?->created_at]);

        $thread->refresh();
        broadcast(new ThreadUpdated($thread))->toOthers();

        return $this->noContentResponse();
    }

    /**
     * Follow a thread
     *
     * Subscribe the authenticated user to the thread's updates.
     *
     * @response 200 {"message": "Thread followed."}
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
     * Unfollow a thread
     *
     * Unsubscribe the authenticated user from the thread's updates.
     *
     * @response 200 {"message": "Thread unfollowed."}
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
