<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{
    public function show(Channel $channel): JsonResponse
    {
        // Cache channel metadata for 10 minutes
        $channelData = cache()->remember(
            "channel.{$channel->id}.metadata",
            now()->addMinutes(10),
            fn () => [
                'id' => $channel->id,
                'name' => $channel->name,
                'topic' => $channel->topic,
                'type' => $channel->type,
            ]
        );

        $query = $channel->messages()->with(['user', 'attachments', 'reactions', 'replyTo.user']);

        if (request()->has('before')) {
            $query->where('id', '<', request()->input('before'));
        }

        $messages = $query
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'channel' => $channelData,
            'messages' => $messages,
            'has_more' => $messages->count() === 50,
        ]);
    }
}
