<?php

namespace App\Http\Controllers;

use App\Events\UserTyping;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TypingController extends Controller
{
    public function __invoke(Request $request, Channel $channel): JsonResponse
    {
        broadcast(new UserTyping(
            $request->user(),
            $channel->id,
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    public function dmTyping(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        // Cache participation check to avoid DB query on every keystroke
        $isParticipant = cache()->remember(
            "dm.{$dmGroup->id}.participant.{$user->id}",
            now()->addMinutes(30),
            fn () => $dmGroup->participants()->where('users.id', $user->id)->exists()
        );

        if (! $isParticipant) {
            abort(403);
        }

        broadcast(new UserTyping(
            $user,
            $dmGroup->id,
            true, // isDm flag
        ))->toOthers();

        return response()->json(['success' => true]);
    }
}
