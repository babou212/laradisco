<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageEdited;
use App\Events\DirectMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateDirectMessageGroupRequest;
use App\Http\Requests\Api\FindDirectMessageGroupRequest;
use App\Http\Requests\Api\MessagePaginateRequest;
use App\Http\Requests\Api\SearchMessagesRequest;
use App\Http\Requests\Api\StoreDirectMessageRequest;
use App\Http\Requests\Api\UpdateDirectMessageRequest;
use App\Http\Resources\DirectMessageGroupResource;
use App\Http\Resources\DirectMessageResource;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Notifications\DirectMessageNotification;
use App\Services\InboxService;
use App\Services\MessageWindowService;
use App\Support\CacheKeys;
use App\Support\Media\AttachmentRebinder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Direct Messages
 */
class DirectMessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MessageWindowService $windowService,
        private readonly AttachmentRebinder $attachmentRebinder,
        private readonly InboxService $inboxService,
    ) {}

    /**
     * List DM groups
     *
     * List all DM groups for the authenticated user.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: participants, lastMessage, lastMessage.user. Example: participants,lastMessage
     * @queryParam sort string Sort field; prefix - for descending. Defaults to -last_message_at. Allowed: last_message_at. Example: -last_message_at
     *
     * @response 200 {"data": [{"type": "direct_message_groups", "id": "12", "attributes": {"name": "alice", "other_user": {"id": "8", "username": "alice", "display_name": "Alice", "avatar_urls": null, "status": "online", "custom_status": null}, "last_message": {"id": 540, "created_at": "2026-06-30T12:00:00.000000Z", "user_id": 8, "content": "see you then"}, "last_message_at": "2026-06-30T12:00:00.000000Z", "created_at": "2026-06-01T09:00:00.000000Z"}}]}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cacheKey = CacheKeys::userDmGroups($user->id);
        $cached = Cache::tags([CacheKeys::userTag($user->id)])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $dmGroups = QueryBuilder::for(
            $user->directMessageGroups()
        )
            ->allowedIncludes('participants', 'lastMessage', 'lastMessage.user')
            ->allowedSorts('last_message_at')
            ->defaultSort('-last_message_at')
            ->with([
                'participants:id,username,status,custom_status',
                'lastMessage.user:id,username,status,custom_status',
            ])
            ->get();

        $response = DirectMessageGroupResource::collection($dmGroups)
            ->includePreviouslyLoadedRelationships()
            ->response();

        Cache::tags([CacheKeys::userTag($user->id)])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_WARM);

        return $response;
    }

    /**
     * Show a DM group with messages
     *
     * Returns the DM group plus its messages oldest→newest. With no anchor, the
     * latest `limit` (default 50) messages are returned; otherwise anchor the
     * window on `before`/`after`/`around` a message id. The group itself is in
     * `meta.dm_group`.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: user, reactions, replyTo, replyTo.user, attachments. Example: user,reactions
     * @queryParam limit integer Page size, 1–100. Defaults to 50. Example: 50
     * @queryParam before integer Return messages older than this message id. Example: 540
     * @queryParam after integer Return messages newer than this message id. Example: 559
     * @queryParam around integer Return a window centred on this message id. Example: 550
     *
     * @responseField data object[] The messages, ordered oldest→newest.
     * @responseField meta.dm_group object The DM group resource.
     * @responseField meta.has_more_before boolean Whether older messages exist before this window.
     * @responseField meta.has_more_after boolean Whether newer messages exist after this window.
     * @responseField meta.oldest_id string Id of the oldest message in `data` (cursor for paging back with `before`).
     * @responseField meta.newest_id string Id of the newest message in `data` (cursor for paging forward with `after`).
     *
     * @response 200 {"data": [{"type": "direct_messages", "id": "558", "attributes": {"dm_group_id": 12, "user_id": 8, "content": "hey there", "reply_to_id": null, "is_pinned": false, "is_edited": false, "created_at": "2026-06-30T12:00:00.000000Z"}, "relationships": {"user": {"data": {"type": "users", "id": "8"}}}}], "meta": {"has_more_before": true, "has_more_after": false, "oldest_id": "558", "newest_id": "558"}}
     */
    public function show(MessagePaginateRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $allowedIncludes = ['user', 'reactions', 'replyTo', 'replyTo.user', 'attachments'];

        $dmGroupMeta = [
            'dm_group' => (new DirectMessageGroupResource(
                $dmGroup->load('participants:id,username,status,custom_status')
            ))->includePreviouslyLoadedRelationships(),
        ];

        $limit = (int) $request->validated('limit', 50);

        if ($around = $request->validated('around')) {
            $target = $dmGroup->messages()->where('id', $around)->first();

            if (! $target) {
                return $this->notFoundResponse('Target message not found in this conversation.');
            }

            /** @var Builder<Model> $windowQuery */
            $windowQuery = QueryBuilder::for(
                $dmGroup->messages()
            )
                ->allowedIncludes(...$allowedIncludes)
                ->getEloquentBuilder();

            $window = $this->windowService->windowAround($windowQuery, $target);

            return $this->dmPaginatedResponse(
                $window['items'],
                $window['hasMoreBefore'],
                $window['hasMoreAfter'],
                $window['oldestId'],
                $window['newestId'],
                $dmGroupMeta,
            );
        }

        $before = $request->validated('before');
        $after = $request->validated('after');

        $base = QueryBuilder::for($dmGroup->messages())
            ->allowedIncludes(...$allowedIncludes)
            ->getEloquentBuilder();

        $includes = (string) $request->query('include', '');
        $isLatestNoArg = $before === null && $after === null;
        $cacheKey = CacheKeys::dmGroupMessages($dmGroup->id).'.latest.l'.$limit.'.'.md5($includes);

        if ($isLatestNoArg) {
            $cached = Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->get($cacheKey);
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

        $response = $this->dmPaginatedResponse(
            $items,
            $hasMoreBefore,
            $hasMoreAfter,
            $oldestId,
            $newestId,
            $dmGroupMeta,
        );

        if ($isLatestNoArg) {
            Cache::tags([CacheKeys::dmGroupTag($dmGroup->id), CacheKeys::dmGroupMessagesTag($dmGroup->id)])
                ->put($cacheKey, $response->getData(true), CacheKeys::TTL_HOT);
        }

        return $response;
    }

    /**
     * @param  Collection<int, Model>  $items
     * @param  array<string, mixed>  $extraMeta
     */
    private function dmPaginatedResponse(
        Collection $items,
        bool $hasMoreBefore,
        bool $hasMoreAfter,
        ?string $oldestId,
        ?string $newestId,
        array $extraMeta,
    ): JsonResponse {
        $response = DirectMessageResource::collection($items)
            ->includePreviouslyLoadedRelationships()
            ->response();

        $payload = $response->getData(true);
        $payload['meta'] = array_merge($payload['meta'] ?? [], $extraMeta, [
            'has_more_before' => $hasMoreBefore,
            'has_more_after' => $hasMoreAfter,
            'oldest_id' => $oldestId,
            'newest_id' => $newestId,
        ]);
        $response->setData($payload);

        return $response;
    }

    /**
     * Send a DM
     *
     * Send a message in a DM group. Pass a unique `client_temp_id` to make
     * retries idempotent. Returns the created message.
     *
     * @response 201 {"data": {"type": "direct_messages", "id": "560", "attributes": {"dm_group_id": 12, "user_id": 7, "content": "hello world", "reply_to_id": null, "is_pinned": false, "is_edited": false, "created_at": "2026-06-30T12:05:00.000000Z"}, "relationships": {"user": {"data": {"type": "users", "id": "7"}}}}}
     */
    public function store(StoreDirectMessageRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        // Idempotent send via client_temp_id — see MessageController::store.
        $clientTempId = $request->validated('client_temp_id');
        if ($clientTempId) {
            $existing = $dmGroup->messages()
                ->where('user_id', $user->id)
                ->where('client_temp_id', $clientTempId)
                ->first();
            if ($existing) {
                $existing->load(['user:id,username,status,custom_status', 'replyTo.user:id,username,status,custom_status']);

                return (new DirectMessageResource($existing))
                    ->includePreviouslyLoadedRelationships()
                    ->response()
                    ->setStatusCode(Response::HTTP_OK);
            }
        }

        $message = $dmGroup->messages()->create([
            'user_id' => $user->id,
            'reply_to_id' => $request->validated('reply_to_id'),
            'client_temp_id' => $clientTempId,
            'content' => $request->validated('content'),
            'link_preview' => $request->validated('link_preview'),
        ]);

        $this->attachmentRebinder->rebind($user, $message, $request->validated('attachment_ids', []));

        $dmGroup->update(['last_message_at' => now()]);

        $message->load(['user:id,username,status,custom_status', 'replyTo.user:id,username']);

        // Flush DM message cache and DM group list cache for all participants
        Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->flush();
        $dmGroup->loadMissing('participants');
        foreach ($dmGroup->participants as $participant) {
            Cache::tags([CacheKeys::userTag($participant->id)])->forget(CacheKeys::userDmGroups($participant->id));
        }

        broadcast(new DirectMessageSent($message))->toOthers();

        $dmGroup->loadMissing('participants');
        $recipients = $dmGroup->participants->where('id', '!=', $user->id);

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new DirectMessageNotification($message)
            );

            // Persist an inbox row per recipient; deleted when the client acks
            // (live for online clients, on reconnect-drain for offline ones).
            $this->inboxService->enqueueForRecipients(
                $recipients->pluck('id'),
                'direct_message',
                $message->id,
                (new DirectMessageSent($message))->broadcastWith()['message'],
            );
        }

        return (new DirectMessageResource($message))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.direct-messages.show', $dmGroup));
    }

    /**
     * Search messages in a DM group
     *
     * Full-text search messages within a DM group. Page-based pagination;
     * `meta.query` echoes the search term.
     *
     * @response 200 {"data": [{"type": "direct_messages", "id": "520", "attributes": {"dm_group_id": 12, "user_id": 8, "content": "the term you searched", "reply_to_id": null, "is_pinned": false, "is_edited": false, "created_at": "2026-06-30T11:00:00.000000Z"}}], "links": {"first": "...", "last": "...", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 30, "total": 1, "query": "term"}}
     */
    public function search(SearchMessagesRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();
        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $query = (string) $request->validated('q');
        $perPage = (int) $request->validated('per_page', 30);

        $paginator = DirectMessage::search($query)
            ->where('direct_message_group_id', $dmGroup->id)
            ->paginate($perPage);

        DirectMessage::query()->getModel()->newCollection($paginator->items())
            ->loadMissing(['user:id,username,status,custom_status', 'attachments']);

        return DirectMessageResource::collection($paginator)
            ->additional(['meta' => ['query' => $query]])
            ->response();
    }

    /**
     * Head check for DM sync
     *
     * Lightweight head check: returns latest message id and (if since_id given)
     * count of newer messages for local-first sync.
     *
     * @queryParam since_id integer If set, also returns how many messages exist newer than this id. Example: 540
     *
     * @response 200 {"data": {"latest_id": 559, "count_since_id": 12}}
     */
    public function head(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();
        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $latestId = $dmGroup->messages()->max('id');
        $sinceId = (int) $request->query('since_id', 0);
        $countSinceId = $sinceId > 0
            ? $dmGroup->messages()->where('id', '>', $sinceId)->count()
            : null;

        return response()->json([
            'data' => [
                'latest_id' => $latestId,
                'count_since_id' => $countSinceId,
            ],
        ]);
    }

    /**
     * Update a DM
     *
     * Edit a DM message. Owner only. Marks the message as edited.
     *
     * @response 200 {"data": {"type": "direct_messages", "id": "560", "attributes": {"dm_group_id": 12, "user_id": 7, "content": "edited content", "reply_to_id": null, "is_pinned": false, "is_edited": true, "edited_at": "2026-06-30T12:10:00.000000Z", "created_at": "2026-06-30T12:05:00.000000Z"}}}
     */
    public function update(UpdateDirectMessageRequest $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if ($message->user_id !== $user->id) {
            return $this->forbiddenResponse('You can only edit your own messages.');
        }

        $message->update([
            'content' => $request->validated('content', $message->content),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->flush();

        broadcast(new DirectMessageEdited($message))->toOthers();

        return (new DirectMessageResource($message))
            ->response();
    }

    /**
     * Delete a DM
     *
     * Delete a DM message. Owner only.
     *
     * @response 204
     */
    public function destroy(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse|Response
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if ($message->user_id !== $user->id) {
            return $this->forbiddenResponse('You can only delete your own messages.');
        }

        $messageId = $message->id;

        $message->update(['content' => null]);
        $message->delete();

        Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->flush();

        broadcast(new DirectMessageDeleted($messageId, $dmGroup->id))->toOthers();

        return $this->noContentResponse();
    }

    /**
     * Find a DM group with a user
     *
     * Find an existing one-on-one DM group with a specific user.
     *
     * @queryParam user_id integer The other user's id. Example: 8
     *
     * @response 200 {"data": {"dm_group_id": 12}}
     */
    public function findDm(FindDirectMessageGroupRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $otherUserId = $request->validated('user_id');

        $existingDm = $this->findOneOnOneDmGroup((int) $currentUser->id, (int) $otherUserId);

        if (! $existingDm) {
            return $this->notFoundResponse('No existing DM group found.');
        }

        return response()->json(['data' => ['dm_group_id' => $existingDm->id]]);
    }

    /**
     * Create a DM group
     *
     * Create a new one-on-one DM group with a user. If a group with that user
     * already exists, returns it (200) as `{"data": {"dm_group_id": <id>}}`
     * instead of creating a duplicate.
     *
     * @response 201 {"data": {"type": "direct_message_groups", "id": "12", "attributes": {"name": "alice", "other_user": {"id": "8", "username": "alice", "display_name": "Alice", "avatar_urls": null, "status": "online", "custom_status": null}, "last_message": null, "last_message_at": null, "created_at": "2026-06-30T12:00:00.000000Z"}}}
     */
    public function createDm(CreateDirectMessageGroupRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $otherUserId = $request->validated('user_id');

        if ($currentUser->id === (int) $otherUserId) {
            return $this->validationErrorResponse('Cannot start a conversation with yourself.');
        }

        $existingDm = $this->findOneOnOneDmGroup((int) $currentUser->id, (int) $otherUserId);

        if ($existingDm) {
            return response()->json(['data' => ['dm_group_id' => $existingDm->id]]);
        }

        $dmGroup = DirectMessageGroup::create([
            'owner_id' => $currentUser->id,
        ]);

        $dmGroup->participants()->attach([$currentUser->id, $otherUserId]);

        return (new DirectMessageGroupResource($dmGroup->load('participants:id,username,status,custom_status')))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.direct-messages.show', $dmGroup));
    }

    private function findOneOnOneDmGroup(int $userA, int $userB): ?DirectMessageGroup
    {
        return DirectMessageGroup::whereHas('participants', fn ($q) => $q->where('users.id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userB))
            ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('users.id', [$userA, $userB]))
            ->first();
    }
}
