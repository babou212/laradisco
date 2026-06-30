<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Typing
 */
class TypingController extends Controller
{
    use ApiResponse;

    /**
     * Send a typing event
     *
     * Broadcast a typing indicator for the authenticated user to other members
     * of the channel.
     *
     * @response 200 {"message": "Typing event sent"}
     */
    public function __invoke(Request $request, Channel $channel): JsonResponse
    {
        broadcast(new UserTyping(
            $request->user(),
            $channel->id,
        ))->toOthers();

        return $this->successResponse(message: 'Typing event sent');
    }

    /**
     * Broadcast a typing event for a DM
     *
     * @group Direct Messages
     *
     * @response 200 {"message": "Typing event sent"}
     */
    public function dmTyping(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        $isParticipant = cache()->remember(
            "dm.{$dmGroup->id}.participant.{$user->id}",
            now()->addMinutes(30),
            fn () => $dmGroup->participants()->where('users.id', $user->id)->exists()
        );

        if (! $isParticipant) {
            return $this->forbiddenResponse();
        }

        broadcast(new UserTyping(
            $user,
            $dmGroup->id,
            true,
        ))->toOthers();

        return $this->successResponse(message: 'Typing event sent');
    }
}
