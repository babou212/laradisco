<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Events\MessagePinned;
use App\Events\MessageUnpinned;
use App\Http\Controllers\Controller;
use App\Http\Resources\DirectMessageResource;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Pins
 */
class PinController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * List pinned messages
     *
     * List pinned messages in a channel, newest pin first.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: user, reactions, attachments. Example: user,reactions
     * @queryParam sort string Sort field; prefix - for descending. Allowed: created_at. Example: -created_at
     *
     * @response 200 {"data": [{"type": "messages", "id": "1858", "attributes": {"channel_id": 42, "user_id": 7, "content": "pinned message", "is_pinned": true, "is_edited": false, "reply_to_id": null, "thread_id": null, "created_at": "2026-06-30T12:00:00.000000Z"}}]}
     */
    public function index(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse();
        }

        $includes = $request->query('include', '');
        $cacheKey = CacheKeys::channelPinnedMessages($channel->id).'.'.md5($includes);
        $cached = Cache::tags([CacheKeys::channelTag($channel->id)])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $pinned = QueryBuilder::for(
            $channel->pinnedMessages()
        )
            ->allowedIncludes('user', 'reactions', 'attachments')
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->get();

        $response = MessageResource::collection($pinned)
            ->includePreviouslyLoadedRelationships()
            ->response();

        Cache::tags([CacheKeys::channelTag($channel->id)])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_COLD);

        return $response;
    }

    /**
     * Pin a message
     *
     * Pin a channel message. Requires the Pin Messages permission.
     *
     * @response 200 {"data": {"type": "messages", "id": "1858", "attributes": {"channel_id": 42, "user_id": 7, "content": "pinned message", "is_pinned": true, "is_edited": false, "reply_to_id": null, "thread_id": null, "created_at": "2026-06-30T12:00:00.000000Z"}}}
     */
    public function pin(Request $request, Channel $channel, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages)) {
            return $this->forbiddenResponse('You do not have permission to pin messages.');
        }

        if ($message->is_pinned) {
            return (new MessageResource($message))
                ->response();
        }

        $message->update(['is_pinned' => true]);

        broadcast(new MessagePinned($channel->id, $message->id, $user->id))->toOthers();

        return (new MessageResource($message->load(['user', 'reactions'])))
            ->includePreviouslyLoadedRelationships()
            ->response();
    }

    /**
     * Unpin a message
     *
     * Unpin a channel message. Requires the Pin Messages permission.
     *
     * @response 204
     */
    public function unpin(Request $request, Channel $channel, Message $message): JsonResponse|Response
    {
        $user = $request->user();

        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        if (! $this->permissionService->userCanInChannel($user, $channel, PermissionFlag::PinMessages)) {
            return $this->forbiddenResponse('You do not have permission to unpin messages.');
        }

        if (! $message->is_pinned) {
            return $this->successResponse(null, 'Message is not pinned.');
        }

        $message->update(['is_pinned' => false]);

        broadcast(new MessageUnpinned($channel->id, $message->id))->toOthers();

        return $this->noContentResponse();
    }

    /**
     * List pinned messages in a DM group
     *
     * @group Direct Messages
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: user, reactions, attachments. Example: user,reactions
     * @queryParam sort string Sort field; prefix - for descending. Allowed: created_at. Defaults to -created_at. Example: -created_at
     *
     * @response 200 {"data": [{"type": "direct_messages", "id": "920", "attributes": {"dm_group_id": 5, "user_id": 7, "content": "pinned dm", "is_pinned": true, "created_at": "2026-06-30T12:00:00.000000Z"}}]}
     */
    public function dmIndex(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $includes = $request->query('include', '');
        $cacheKey = CacheKeys::dmGroupPinnedMessages($dmGroup->id).'.'.md5($includes);
        $cached = Cache::tags([CacheKeys::dmGroupTag($dmGroup->id)])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $pinned = QueryBuilder::for(
            $dmGroup->messages()->where('is_pinned', true)
        )
            ->allowedIncludes('user', 'reactions', 'attachments')
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->get();

        $response = DirectMessageResource::collection($pinned)
            ->includePreviouslyLoadedRelationships()
            ->response();

        Cache::tags([CacheKeys::dmGroupTag($dmGroup->id)])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_COLD);

        return $response;
    }

    /**
     * Pin a direct message
     *
     * @group Direct Messages
     *
     * @response 200 {"data": {"type": "direct_messages", "id": "920", "attributes": {"dm_group_id": 5, "user_id": 7, "content": "pinned dm", "is_pinned": true, "created_at": "2026-06-30T12:00:00.000000Z"}}}
     */
    public function dmPin(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if ($message->is_pinned) {
            return (new DirectMessageResource($message))
                ->response();
        }

        $message->update(['is_pinned' => true]);

        broadcast(new MessagePinned($dmGroup->id, $message->id, $user->id, true))->toOthers();

        return (new DirectMessageResource($message->load(['user', 'reactions'])))
            ->includePreviouslyLoadedRelationships()
            ->response();
    }

    /**
     * Unpin a direct message
     *
     * @group Direct Messages
     *
     * @response 204
     */
    public function dmUnpin(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse|Response
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if (! $message->is_pinned) {
            return $this->successResponse(null, 'Message is not pinned.');
        }

        $message->update(['is_pinned' => false]);

        broadcast(new MessageUnpinned($dmGroup->id, $message->id, true))->toOthers();

        return $this->noContentResponse();
    }
}
