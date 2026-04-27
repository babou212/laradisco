<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\AttachmentStatus;
use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageEdited;
use App\Events\DirectMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateDirectMessageGroupRequest;
use App\Http\Requests\Api\CursorPaginateRequest;
use App\Http\Requests\Api\FindDirectMessageGroupRequest;
use App\Http\Requests\Api\StoreDirectMessageRequest;
use App\Http\Requests\Api\UpdateDirectMessageRequest;
use App\Http\Resources\DirectMessageGroupResource;
use App\Http\Resources\DirectMessageResource;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\EncryptedAttachment;
use App\Notifications\DirectMessageNotification;
use App\Services\MessageWindowService;
use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class DirectMessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MessageWindowService $windowService,
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
     * Show a specific DM group with messages.
     */
    public function show(CursorPaginateRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $allowedIncludes = ['user', 'reactions', 'replyTo', 'replyTo.user', 'encryptedAttachments'];

        $dmGroupMeta = [
            'dm_group' => (new DirectMessageGroupResource(
                $dmGroup->load('participants:id,username,name,nickname,status,custom_status')
            ))->includePreviouslyLoadedRelationships(),
        ];

        $around = $request->query('around');
        if ($around) {
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

            $response = DirectMessageResource::collection($window['items'])
                ->includePreviouslyLoadedRelationships()
                ->additional(['meta' => $dmGroupMeta])
                ->response();

            $payload = $response->getData(true);
            $payload['links'] = [
                'prev' => $window['prevCursor'] ? $request->url().'?cursor='.$window['prevCursor'] : null,
                'next' => $window['nextCursor'] ? $request->url().'?cursor='.$window['nextCursor'] : null,
            ];
            $response->setData($payload);

            return $response;
        }

        // Local-first delta sync — see MessageController::index for parity.
        $sinceId = $request->query('since_id');
        if ($sinceId !== null) {
            $messages = QueryBuilder::for(
                $dmGroup->messages()->where('id', '>', (int) $sinceId)
            )
                ->allowedIncludes(...$allowedIncludes)
                ->orderBy('id', 'asc')
                ->cursorPaginate(50);

            return DirectMessageResource::collection($messages)
                ->includePreviouslyLoadedRelationships()
                ->additional(['meta' => $dmGroupMeta])
                ->response();
        }

        $cursor = $request->query('cursor');
        $includes = $request->query('include', '');
        $cacheKey = CacheKeys::dmGroupMessages($dmGroup->id).'.'.md5($includes);

        if (! $cursor) {
            $cached = Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->get($cacheKey);
            if ($cached) {
                return response()->json($cached);
            }
        }

        $messages = QueryBuilder::for(
            $dmGroup->messages()
        )
            ->allowedIncludes(...$allowedIncludes)
            ->allowedSorts('created_at')
            ->defaultSort('created_at')
            ->cursorPaginate(50);

        $response = DirectMessageResource::collection($messages)
            ->includePreviouslyLoadedRelationships()
            ->additional(['meta' => $dmGroupMeta])
            ->response();

        if (! $cursor) {
            Cache::tags([CacheKeys::dmGroupTag($dmGroup->id), CacheKeys::dmGroupMessagesTag($dmGroup->id)])
                ->put($cacheKey, $response->getData(true), CacheKeys::TTL_HOT);
        }

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
            'sender_device_id' => $request->validated('sender_device_id'),
            'client_temp_id' => $clientTempId,
            'message_bytes' => $request->validated('message_bytes'),
            'epoch' => $request->validated('epoch', 0),
        ]);

        $attachmentIds = $request->validated('attachment_ids', []);
        if (! empty($attachmentIds)) {
            EncryptedAttachment::where('user_id', $user->id)
                ->where('status', AttachmentStatus::Attached)
                ->whereNull('attachable_type')
                ->whereIn('id', $attachmentIds)
                ->update([
                    'attachable_type' => DirectMessage::class,
                    'attachable_id' => $message->id,
                ]);
        }

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
            'sender_device_id' => $request->validated('sender_device_id', $message->sender_device_id),
            'message_bytes' => $request->validated('message_bytes', $message->message_bytes),
            'epoch' => $request->validated('epoch', $message->epoch),
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

        $message->update(['history_ciphertext' => null, 'message_bytes' => null]);
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
