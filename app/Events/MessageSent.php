<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The queue the broadcast event should be placed on.
     */
    public string $queue = 'broadcasting';

    /**
     * Create a new event instance.
     */
    public function __construct(public Message $message)
    {
        $this->message->load('user');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('channel.'.$this->message->channel_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'channel_id' => $this->message->channel_id,
                'user_id' => $this->message->user_id,
                'content' => $this->message->message_bytes,
                'sender_device_id' => $this->message->sender_device_id,
                'reply_to_id' => $this->message->reply_to_id,
                'thread_id' => $this->message->thread_id,
                'is_pinned' => $this->message->is_pinned,
                'is_edited' => $this->message->is_edited ?? false,
                'reactions' => [],
                'user' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'username' => $this->message->user->username,
                    'name' => $this->message->user->name,
                    'nickname' => $this->message->user->nickname,
                    'avatar_path' => $this->message->user->avatar_path,
                ] : null,
                'created_at' => $this->message->created_at?->toISOString(),
                'updated_at' => $this->message->updated_at?->toISOString(),
            ],
        ];
    }
}
