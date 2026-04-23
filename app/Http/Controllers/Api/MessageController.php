<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\AttachmentStatus;
use App\Enums\ModerationAction;
use App\Enums\PermissionFlag;
use App\Events\ChannelActivity;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CursorPaginateRequest;
use App\Http\Requests\Api\StoreChannelMessageRequest;
use App\Http\Requests\Api\UpdateChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\EncryptedAttachment;
use App\Models\Message;
use App\Services\MentionService;
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

class MessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MentionService $mentionService,
        private readonly PermissionService $permissionService,
        private readonly ModerationAuditService $auditService,
        private readonly MessageWindowService $windowService,
    ) {}

    /**
     * Get cursor-paginated messages for a channel.
     */
    public function index(CursorPaginateRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        $this->authorize('viewChannel', [Message::class, $channel]);

        $allowedIncludes = [
            'user',
            'encryptedAttachments',
            'reactions',
            'replyTo',
            'replyTo.user',
            'threadStarted',
            'threadStarted.latestReply',
            'threadStarted.latestReply.user',
            'threadStarted.followers',
        ];

        $around = $request->query('around');
        if ($around) {
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

            return $this->windowResponse(
                $window,
                $request->url()
            );
        }

        // Only cache the initial load (no cursor = latest messages)
        $cursor = $request->query('cursor');
        $includes = $request->query('include', '');
        $cacheKey = CacheKeys::channelMessages($channel->id).'.'.md5($includes);

        if (! $cursor) {
            $cached = Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->get($cacheKey);
            if ($cached) {
                return response()->json($cached);
            }
        }

        $messages = QueryBuilder::for(
            $channel->messages()->whereNull('thread_id')
        )
            ->allowedIncludes(...$allowedIncludes)
            ->allowedSorts('created_at')
            ->defaultSort('created_at')
            ->cursorPaginate(50);

        $response = MessageResource::collection($messages)
            ->includePreviouslyLoadedRelationships()
            ->response();

        if (! $cursor) {
            Cache::tags([CacheKeys::channelTag($channel->id), CacheKeys::channelMessagesTag($channel->id)])
                ->put($cacheKey, $response->getData(true), CacheKeys::TTL_HOT);
        }

        return $response;
    }

    /**
     * Build a listing-shaped response from a MessageWindowService result.
     *
     * @param  array{items: Collection<int, Model>, prevCursor: ?string, nextCursor: ?string}  $window
     */
    private function windowResponse(array $window, string $baseUrl): JsonResponse
    {
        $response = MessageResource::collection($window['items'])
            ->includePreviouslyLoadedRelationships()
            ->response();

        $payload = $response->getData(true);
        $payload['links'] = [
            'prev' => $window['prevCursor'] ? $baseUrl.'?cursor='.$window['prevCursor'] : null,
            'next' => $window['nextCursor'] ? $baseUrl.'?cursor='.$window['nextCursor'] : null,
        ];
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

        $message = $channel->messages()->create([
            'user_id' => $user->id,
            'reply_to_id' => $request->validated('reply_to_id'),
            'sender_device_id' => $request->validated('sender_device_id'),
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
                    'attachable_type' => Message::class,
                    'attachable_id' => $message->id,
                ]);
        }

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
            'sender_device_id' => $request->validated('sender_device_id', $message->sender_device_id),
            'message_bytes' => $request->validated('message_bytes', $message->message_bytes),
            'epoch' => $request->validated('epoch', $message->epoch),
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

        $message->update(['history_ciphertext' => null, 'message_bytes' => null]);
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
