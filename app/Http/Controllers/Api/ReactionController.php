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

class ReactionController extends Controller
{
    use ApiResponse;

    /**
     * Toggle a reaction on a channel message.
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

            Cache::tags([CacheKeys::channelTag($channel->id)])->flush();

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

        Cache::tags([CacheKeys::channelTag($channel->id)])->flush();

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
     * Toggle a reaction on a thread message.
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

            Cache::tags([CacheKeys::channelTag($channel->id), CacheKeys::threadTag($thread->id)])->flush();

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

        Cache::tags([CacheKeys::channelTag($channel->id), CacheKeys::threadTag($thread->id)])->flush();

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
     * Toggle a reaction on a direct message.
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

            Cache::tags([CacheKeys::dmGroupTag($dmGroup->id)])->flush();

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

        Cache::tags([CacheKeys::dmGroupTag($dmGroup->id)])->flush();

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
