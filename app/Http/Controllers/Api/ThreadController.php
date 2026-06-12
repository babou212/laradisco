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
     * List thread messages: latest 50 by default, or anchored on
     * before/after/around message ids. Response is always ASC.
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
