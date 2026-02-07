<?php

namespace App\Http\Controllers;

use App\Events\ReactionToggled;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function toggle(Request $request, Channel $channel, Message $message): JsonResponse
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

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

            return response()->json(['added' => false]);
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

        return response()->json(['added' => true, 'reaction' => $reaction]);
    }
}
