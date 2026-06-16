<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Thread;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(public Thread $thread)
    {
        $this->thread->load(['latestReply.user:id,username,status,custom_status']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('channel.'.$this->thread->channel_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->thread->message_id,
            'thread' => [
                'id' => $this->thread->id,
                'message_count' => $this->thread->message_count,
                'last_message_at' => $this->thread->last_message_at?->toISOString(),
                'last_reply' => $this->thread->latestReply
                    ? (new MessageResource($this->thread->latestReply))->resolve()
                    : null,
            ],
        ];
    }
}
