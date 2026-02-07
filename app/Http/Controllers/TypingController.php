<?php

namespace App\Http\Controllers;

use App\Events\UserTyping;
use App\Models\Channel;
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
}
