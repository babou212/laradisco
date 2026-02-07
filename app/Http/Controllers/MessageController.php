<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Channel $channel): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message = $channel->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return back();
    }

    public function update(UpdateMessageRequest $request, Channel $channel, Message $message): JsonResponse
    {
        $message->update([
            'content' => $request->validated('content'),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new MessageEdited($message))->toOthers();

        return response()->json(['message' => $message]);
    }

    public function destroy(Request $request, Channel $channel, Message $message): JsonResponse
    {
        if ($request->user()->id !== $message->user_id) {
            abort(403);
        }

        $messageId = $message->id;
        $message->delete();

        broadcast(new MessageDeleted($messageId, $channel->id))->toOthers();

        return response()->json(['success' => true]);
    }
}
