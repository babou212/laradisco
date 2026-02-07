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

        $directMessages = $request->user()
            ->directMessageGroups()
            ->with(['participants' => function ($query) use ($request) {
                $query->where('users.id', '!=', $request->user()->id);
            }])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name ?? $group->participants->pluck('name')->join(', '),
                ];
            });

        // Get the channel to display (from query param or first available)
        $channelId = $request->query('channel');
        $channel = null;

        if ($channelId) {
            $channel = Channel::with(['messages' => function ($query) {
                $query->with(['user', 'attachments', 'reactions'])
                    ->orderBy('created_at', 'asc')
                    ->limit(50);
            }])->find($channelId);
        }

        if (! $channel) {
            $channel = Channel::with(['messages' => function ($query) {
                $query->with(['user', 'attachments', 'reactions'])
                    ->orderBy('created_at', 'asc')
                    ->limit(50);
            }])
                ->where('is_private', false)
                ->orderBy('position')
                ->first();
        }

        return Inertia::render('Chat', [
            'categories' => $categories,
            'directMessages' => $directMessages,
            'currentChannel' => $channel ? [
                'id' => $channel->id,
                'name' => $channel->name,
                'topic' => $channel->topic,
                'messages' => $channel->messages,
            ] : null,
        ]);
    }
}
