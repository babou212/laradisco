<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        return $this->performSearch($request);
    }

    protected function performSearch(Request $request)
    {
        $query = $request->input('query');
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);
        $perPage = 20;

        $filters = [];
        
        if (preg_match('/from:(\w+)/', $query, $matches)) {
            $username = $matches[1];
            $query = trim(str_replace($matches[0], '', $query));
            $user = User::where('username', 'like', "%{$username}%")->first();
            if ($user) {
                $filters['user_id'] = $user->id;
            }
        }

        if (preg_match('/in:([\w-]+)/', $query, $matches)) {
            $channelName = $matches[1];
            $query = trim(str_replace($matches[0], '', $query));
            $channel = \App\Models\Channel::where('name', 'like', "%{$channelName}%")->orWhere('slug', $channelName)->first();
            if ($channel) {
                $filters['channel_id'] = $channel->id;
            }
        }

        if (str_contains($query, 'has:attachment')) {
            $query = trim(str_replace('has:attachment', '', $query));
            $filters['has_attachment'] = true;
        }

        $channelMessages = collect();
        if ($type === 'all' || $type === 'channel') {
            $messageSearch = Message::search($query);
            foreach ($filters as $key => $value) {
                $messageSearch->where($key, $value);
            }

            $channelMessages = $messageSearch->take($perPage * 2)->get();
            $channelMessages->load(['user', 'channel', 'attachments']);
        }

        $directMessages = collect();
        if (($type === 'all' || $type === 'dm') && !isset($filters['channel_id'])) {
            $dmSearch = DirectMessage::search($query);
            
            $dmSearch->where('participant_ids', auth()->id());
            
            if (isset($filters['user_id'])) {
                $dmSearch->where('user_id', $filters['user_id']);
            }

            $directMessages = $dmSearch->take($perPage * 2)->get();
            $directMessages->load(['user', 'group.participants', 'group']); 
        }

        $allMessages = $channelMessages->concat($directMessages);
        
        $sortedMessages = $allMessages->sortByDesc('created_at')->values();

        $sliced = $sortedMessages->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json(new LengthAwarePaginator(
            $sliced,
            $sortedMessages->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        ));
    }
}
