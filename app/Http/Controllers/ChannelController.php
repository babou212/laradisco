<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{
    public function show(Channel $channel): JsonResponse
    {
        $messages = $channel->messages()
            ->with(['user', 'attachments', 'reactions', 'replyTo.user'])
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        return response()->json([
            'channel' => $channel,
            'messages' => $messages,
        ]);
    }
}
