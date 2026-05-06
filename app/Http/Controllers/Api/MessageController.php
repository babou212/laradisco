<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Enums\PermissionFlag;
use App\Events\ChannelActivity;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MessagePaginateRequest;
use App\Http\Requests\Api\SearchMessagesRequest;
use App\Http\Requests\Api\StoreChannelMessageRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\Message;
use App\Services\MentionService;
use App\Services\MessageWindowService;
use App\Services\ModerationAuditService;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use App\Support\Media\AttachmentRebinder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
        private readonly ModerationAuditService $auditService,
        private readonly MessageWindowService $windowService,
        private readonly AttachmentRebinder $attachmentRebinder,
    ) {}

    /**
     * List channel messages: latest 50 by default, or anchored on
     * before/after/around message ids. Response is always ASC.
     */
    public function index(MessagePaginateRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('viewChannel', [Message::class, $channel]);

        $allowedIncludes = [
            'user',
            'attachments',
            'reactions',
            'replyTo',
            'replyTo.user',
            'threadStarted',
            'threadStarted.latestReply',
            'threadStarted.latestReply.user',
            'threadStarted.followers',
        ];

        $limit = (int) $request->validated('limit', 50);

        if ($around = $request->validated('around')) {
            $target = $channel->messages()
                ->whereNull('thread_id')
                ->where('id', $around)
                ->first();

            if (! $target) {
                return $this->notFoundResponse('Target message not found in this channel.');
            }

            /** @var Builder<Model> $windowQuery */
            $windowQuery = QueryBuilder::for(
                $channel->messages()->whereNull('thread_id')
            )
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

        $base = QueryBuilder::for($channel->messages()->whereNull('thread_id'))
            ->allowedIncludes(...$allowedIncludes)
            ->getEloquentBuilder();

        $includes = (string) $request->query('include', '');
        $isLatestNoArg = $before === null && $after === null;
        $cacheKey = CacheKeys::channelMessages($channel->id).'.latest.l'.$limit.'.'.md5($includes);

        if ($isLatestNoArg) {
            $cached = Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->get($cacheKey);
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
            Cache::tags([CacheKeys::channelTag($channel->id), CacheKeys::channelMessagesTag($channel->id)])
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
     * Store a new channel message.
     */
    public function store(StoreChannelMessageRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        $this->authorize('send', [Message::class, $channel]);

        // Idempotent send: if the client supplied a client_temp_id and we
        // already created a row for it, return that row instead of creating
        // a duplicate. This handles outbox retries where the original 2xx
        // response was lost in transit.
        $clientTempId = $request->validated('client_temp_id');
        if ($clientTempId) {
            $existing = $channel->messages()
                ->where('user_id', $user->id)
                ->where('client_temp_id', $clientTempId)
                ->first();
            if ($existing) {
                $existing->load(['user:id,username,name,nickname,status,custom_status', 'replyTo.user:id,username,name,nickname,status,custom_status']);

                return (new MessageResource($existing))
                    ->includePreviouslyLoadedRelationships()
                    ->response()
                    ->setStatusCode(Response::HTTP_OK)
                    ->header('Location', route('api.channels.messages.store', $channel).'/'.$existing->id);
            }
        }

        $message = $channel->messages()->create([
            'user_id' => $user->id,
            'reply_to_id' => $request->validated('reply_to_id'),
            'client_temp_id' => $clientTempId,
            'content' => $request->validated('content'),
        ]);

        $this->attachmentRebinder->rebind($user, $message, $request->validated('attachment_ids', []));

        $message->load(['user:id,username,name,nickname,status,custom_status', 'replyTo.user:id,username,name,nickname,status,custom_status']);

        Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->flush();

        broadcast(new MessageSent($message))->toOthers();

        $recipientIds = $this->permissionService->getUsersWithChannelAccess($channel);
        $createdAt = $message->created_at?->toISOString() ?? now()->toISOString();
        foreach ($recipientIds as $recipientId) {
            if ($recipientId === $user->id) {
                continue;
            }
            broadcast(new ChannelActivity($recipientId, $channel->id, $createdAt));
        }

        $this->mentionService->processMentionsFromMetadata(
            $message,
            $request->validated('mention_user_ids', []),
            $request->validated('mention_everyone', false),
            $request->validated('mention_here', false),
        );

        return (new MessageResource($message))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.channels.messages.store', $channel).'/'.$message->id);
    }

    /**
     * Lightweight head check for local-first sync. Returns the channel's
     * largest message id and (optionally) how many newer messages exist
     * past `since_id`. Single indexed MAX/COUNT — cheap enough to call on
     * every channel open.
     */
    public function head(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('viewChannel', [Message::class, $channel]);

        $latestId = $channel->messages()->whereNull('thread_id')->max('id');
        $sinceId = (int) $request->query('since_id', 0);
        $countSinceId = $sinceId > 0
            ? $channel->messages()->whereNull('thread_id')->where('id', '>', $sinceId)->count()
            : null;

        return response()->json([
            'data' => [
                'latest_id' => $latestId,
                'count_since_id' => $countSinceId,
            ],
        ]);
    }

    /**
     * Full-text search messages within a channel.
     */
    public function search(SearchMessagesRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('viewChannel', [Message::class, $channel]);

        $query = (string) $request->validated('q');
        $perPage = (int) $request->validated('per_page', 30);

        $paginator = Message::search($query)
            ->where('channel_id', $channel->id)
            ->paginate($perPage);

        Message::query()->getModel()->newCollection($paginator->items())
            ->loadMissing(['user:id,username,name,nickname,status,custom_status', 'attachments']);

        return MessageResource::collection($paginator)
            ->additional(['meta' => ['query' => $query]])
            ->response();
    }

    /**
     * Mark a channel as read for the authenticated user.
     */
    public function markRead(Request $request, Channel $channel): JsonResponse|Response
    {
        $user = $request->user();

        $this->authorize('viewChannel', [Message::class, $channel]);

        $user->channels()->syncWithoutDetaching([
            $channel->id => ['last_read_at' => now()],
        ]);

        Cache::tags([CacheKeys::userTag($user->id)])->flush();

        return $this->noContentResponse();
    }

    /**
     * Update a channel message (owner only).
     */
    public function update(UpdateChannelMessageRequest $request, Channel $channel, Message $message): JsonResponse
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        $this->authorize('update', $message);

        $message->update([
            'content' => $request->validated('content', $message->content),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->flush();

        broadcast(new MessageEdited($message))->toOthers();

        return (new MessageResource($message))
            ->response();
    }

    /**
     * Delete a channel message (owner or permission-holder).
     */
    public function destroy(Request $request, Channel $channel, Message $message): JsonResponse|Response
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        $this->authorize('delete', [$message, $channel]);

        $isOwner = $request->user()->id === $message->user_id;
        $canManage = ! $isOwner && $this->permissionService->userCanInChannel(
            $request->user(), $channel, PermissionFlag::ManageMessages
        );

        $messageId = $message->id;
        $messageUserId = $message->user_id;

        $message->update(['content' => null]);
        $message->delete();

        if (! $isOwner && $canManage) {
            $this->auditService->log(
                actorId: $request->user()->id,
                action: ModerationAction::MessageDelete,
                targetUserId: $messageUserId,
                targetResourceId: $messageId,
                targetResourceType: 'message',
                metadata: [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                ],
            );
        }

        Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->flush();

        broadcast(new MessageDeleted($messageId, $channel->id))->toOthers();

        return $this->noContentResponse();
    }
}
