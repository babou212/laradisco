<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Channel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::with(['channels' => function ($query) {
            $query->where('is_private', false)
                ->orderBy('position');
        }])
            ->orderBy('position')
            ->get();

        // Check if viewing specific channel
        $channelId = $request->query('channel');
        $channel = null;

        if ($channelId) {
            $channel = Channel::with(['messages' => function ($query) {
                $query->with(['user', 'attachments', 'reactions', 'replyTo.user'])
                    ->orderBy('created_at', 'asc')
                    ->limit(50);
            }])->find($channelId);
        }

        if (! $channel) {
            $channel = Channel::with(['messages' => function ($query) {
                $query->with(['user', 'attachments', 'reactions', 'replyTo.user'])
                    ->orderBy('created_at', 'asc')
                    ->limit(50);
            }])
                ->where('is_private', false)
                ->orderBy('position')
                ->first();
        }

        if ($channel) {
            $channel = [
                'id' => $channel->id,
                'name' => $channel->name,
                'topic' => $channel->topic,
                'messages' => $channel->messages,
            ];
        }

        return Inertia::render('Chat', [
            'categories' => $categories,
            'currentChannel' => $channel,
        ]);
    }
}
