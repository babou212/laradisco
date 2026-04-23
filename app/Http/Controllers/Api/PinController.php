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

class PinController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * List pinned messages in a channel.
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
            ->allowedIncludes('user', 'reactions')
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
     * Pin a channel message.
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
     * Unpin a channel message.
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
     * List pinned messages in a DM group.
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
            ->allowedIncludes('user', 'reactions')
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
     * Pin a direct message.
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
     * Unpin a direct message.
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
