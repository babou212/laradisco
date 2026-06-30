<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Events\ReactionToggled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ToggleReactionRequest;
use App\Http\Resources\ReactionResource;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\Thread;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Reactions
 */
class ReactionController extends Controller
{
    use ApiResponse;

    /**
     * Toggle a channel reaction
     *
     * Add or remove the authenticated user's reaction for an emoji on a channel
     * message. Returns 201 with the created reaction when added, or 200 with
     * `meta.added: false` when an existing reaction is removed.
     *
     * @response 201 {"data": {"type": "reactions", "id": "55", "attributes": {"user_id": 7, "emoji": "👍", "message_id": 1858, "created_at": "2026-06-30T12:00:00.000000Z"}}, "meta": {"added": true}}
     * @response 200 {"meta": {"added": false}}
     */
    public function toggle(ToggleReactionRequest $request, Channel $channel, Message $message): JsonResponse
    {
        if ($message->channel_id !== $channel->id) {
            return $this->notFoundResponse('Message not found in this channel.');
        }

        $validated = $request->validated();

        $user = $request->user();

        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();

            Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->flush();

            broadcast(new ReactionToggled($channel->id, [
                'id' => $existing->id,
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ], false))->toOthers();

            return response()->json([
                'meta' => ['added' => false],
            ]);
        }

        $reaction = $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        Cache::tags([CacheKeys::channelMessagesTag($channel->id)])->flush();

        broadcast(new ReactionToggled($channel->id, [
            'id' => $reaction->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ], true))->toOthers();

        return (new ReactionResource($reaction))
            ->additional(['meta' => ['added' => true]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Toggle a thread reaction
     *
     * Add or remove the authenticated user's reaction for an emoji on a thread
     * reply. Returns 201 with the created reaction when added, or 200 with
     * `meta.added: false` when an existing reaction is removed.
     *
     * @response 201 {"data": {"type": "reactions", "id": "56", "attributes": {"user_id": 7, "emoji": "🎉", "message_id": 1861, "created_at": "2026-06-30T12:16:00.000000Z"}}, "meta": {"added": true}}
     * @response 200 {"meta": {"added": false}}
     */
    public function threadToggle(ToggleReactionRequest $request, Channel $channel, Thread $thread, Message $message): JsonResponse
    {
        if ($thread->channel_id !== $channel->id) {
            return $this->notFoundResponse('Thread not found in this channel.');
        }

        if ($message->thread_id !== $thread->id) {
            return $this->notFoundResponse('Message not found in this thread.');
        }

        $validated = $request->validated();

        $user = $request->user();

        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();

            Cache::tags([CacheKeys::channelMessagesTag($channel->id), CacheKeys::threadMessagesTag($thread->id)])->flush();

            broadcast(new ReactionToggled($channel->id, [
                'id' => $existing->id,
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ], false, threadId: $thread->id))->toOthers();

            return response()->json([
                'meta' => ['added' => false],
            ]);
        }

        $reaction = $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        Cache::tags([CacheKeys::channelMessagesTag($channel->id), CacheKeys::threadMessagesTag($thread->id)])->flush();

        broadcast(new ReactionToggled($channel->id, [
            'id' => $reaction->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ], true, threadId: $thread->id))->toOthers();

        return (new ReactionResource($reaction))
            ->additional(['meta' => ['added' => true]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Toggle a reaction on a direct message
     *
     * @group Direct Messages
     *
     * Adds the reaction if absent (201) or removes it if present (200). `meta.added` indicates which happened.
     *
     * @response 201 {"data": {"type": "reactions", "id": "55", "attributes": {"user_id": 7, "emoji": "👍", "message_id": 920, "created_at": "2026-06-30T12:00:00.000000Z"}}, "meta": {"added": true}}
     */
    public function dmToggle(ToggleReactionRequest $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        $validated = $request->validated();

        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();

            Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->flush();

            broadcast(new ReactionToggled($dmGroup->id, [
                'id' => $existing->id,
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ], false, true))->toOthers();

            return response()->json([
                'meta' => ['added' => false],
            ]);
        }

        $reaction = $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        Cache::tags([CacheKeys::dmGroupMessagesTag($dmGroup->id)])->flush();

        broadcast(new ReactionToggled($dmGroup->id, [
            'id' => $reaction->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ], true, true))->toOthers();

        return (new ReactionResource($reaction))
            ->additional(['meta' => ['added' => true]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
