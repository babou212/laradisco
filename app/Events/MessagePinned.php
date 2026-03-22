<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagePinned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The queue the broadcast event should be placed on.
     */
    public string $queue = 'broadcasting';

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $channelId,
        public int $messageId,
        public int $pinnedByUserId,
        public bool $isDm = false,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channelName = $this->isDm ? 'direct-message.'.$this->channelId : 'channel.'.$this->channelId;

        return [
            new PresenceChannel($channelName),
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
            'message_id' => $this->messageId,
            'pinned_by' => $this->pinnedByUserId,
        ];
    }
}
