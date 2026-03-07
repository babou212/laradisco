<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Events\ReactionToggled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ToggleReactionRequest;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

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

            broadcast(new ReactionToggled($channel->id, [
                'id' => $existing->id,
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ], false))->toOthers();

            return $this->successResponse(['added' => false], 'Reaction removed');
        }

        $reaction = $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        broadcast(new ReactionToggled($channel->id, [
            'id' => $reaction->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ], true))->toOthers();

        return $this->createdResponse(['added' => true, 'reaction' => $reaction]);
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

            broadcast(new ReactionToggled($dmGroup->id, [
                'id' => $existing->id,
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ], false, true))->toOthers();

            return $this->successResponse(['added' => false], 'Reaction removed');
        }

        $reaction = $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        broadcast(new ReactionToggled($dmGroup->id, [
            'id' => $reaction->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ], true, true))->toOthers();

        return $this->createdResponse(['added' => true, 'reaction' => $reaction]);
    }
}
