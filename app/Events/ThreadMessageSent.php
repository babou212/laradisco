<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(public Message $message)
    {
        $this->message->load('user');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('thread.'.$this->message->thread_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'channel_id' => $this->message->channel_id,
                'user_id' => $this->message->user_id,
                'thread_id' => $this->message->thread_id,
                'sender_device_id' => $this->message->sender_device_id,
                'reply_to_id' => $this->message->reply_to_id,
                'user' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'username' => $this->message->user->username,
                    'name' => $this->message->user->name,
                    'nickname' => $this->message->user->nickname,
                    'avatar_urls' => $this->message->user->avatar_urls,
                ] : null,
                'created_at' => $this->message->created_at?->toISOString(),
                'updated_at' => $this->message->updated_at?->toISOString(),
            ],
        ];
    }
}
