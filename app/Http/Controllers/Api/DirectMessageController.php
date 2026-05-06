<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageEdited;
use App\Events\DirectMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateDirectMessageGroupRequest;
use App\Http\Requests\Api\MessagePaginateRequest;
use App\Http\Requests\Api\FindDirectMessageGroupRequest;
use App\Http\Requests\Api\SearchMessagesRequest;
use App\Http\Requests\Api\StoreDirectMessageRequest;
use App\Http\Requests\Api\UpdateDirectMessageRequest;
use App\Http\Resources\DirectMessageGroupResource;
use App\Http\Resources\DirectMessageResource;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Notifications\DirectMessageNotification;
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

class DirectMessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MessageWindowService $windowService,
        private readonly AttachmentRebinder $attachmentRebinder,
    ) {}

    /**
     * List all DM groups for the authenticated user.
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
                'participants:id,username,name,nickname,status,custom_status',
                'lastMessage.user:id,username,name,nickname,status,custom_status',
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
     * Show a specific DM group with messages. Latest 50 by default, or
     * anchored on before/after/around message ids. Response is always ASC.
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
                $dmGroup->load('participants:id,username,name,nickname,status,custom_status')
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
     * Send a message in a DM group.
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
                $existing->load(['user:id,username,name,nickname,status,custom_status', 'replyTo.user:id,username,name,nickname,status,custom_status']);

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
        ]);

        $this->attachmentRebinder->rebind($user, $message, $request->validated('attachment_ids', []));

        $dmGroup->update(['last_message_at' => now()]);

        $message->load(['user:id,username,name,nickname,status,custom_status', 'replyTo.user:id,username,name,nickname']);

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
        }

        return (new DirectMessageResource($message))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.direct-messages.show', $dmGroup));
    }

    /**
     * Full-text search messages within a DM group.
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
            ->loadMissing(['user:id,username,name,nickname,status,custom_status', 'attachments']);

        return DirectMessageResource::collection($paginator)
            ->additional(['meta' => ['query' => $query]])
            ->response();
    }

    /**
     * Lightweight head check: returns latest message id and (if since_id given)
     * count of newer messages for local-first sync.
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
     * Update a DM message.
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
     * Delete a DM message.
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
     * Find an existing DM group with a specific user.
     *
     * GET /direct-messages/find?user_id=X
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
     * Create a new DM group with a user.
     *
     * POST /direct-messages
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

        return (new DirectMessageGroupResource($dmGroup->load('participants:id,username,name,nickname,status,custom_status')))
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
