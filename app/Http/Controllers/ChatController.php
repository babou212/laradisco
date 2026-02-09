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
            $channel = Channel::find($channelId);
        }

        if (! $channel) {
            $channel = Channel::where('is_private', false)
                ->orderBy('position')
                ->first();
        }

        $channelData = null;
        $messages = null;
        
        if ($channel) {
            $channelData = [
                'id' => $channel->id,
                'name' => $channel->name,
                'topic' => $channel->topic,
            ];
            
            $messages = Inertia::scroll(fn () => 
                $channel->messages()
                    ->with(['user', 'attachments', 'reactions', 'replyTo.user'])
                    ->orderBy('created_at', 'asc')
                    ->cursorPaginate(50)
            );
        }

        $directMessages = $request->user()
            ?->directMessageGroups()
            ->with(['participants', 'lastMessage'])
            ->orderByDesc('updated_at')
            ->get() ?? [];

        return Inertia::render('Chat', [
            'categories' => $categories,
            'currentChannel' => $channelData,
            'messages' => $messages,
            'directMessages' => $directMessages,
        ]);
    }
}
