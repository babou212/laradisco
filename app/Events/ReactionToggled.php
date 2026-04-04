<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionToggled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The queue the broadcast event should be placed on.
     */
    public string $queue = 'broadcasting';

    /**
     * Create a new event instance.
     *
     * @param  array{id: int, message_id: int, user_id: int, emoji: string}  $reaction
     */
    public function __construct(
        public int $channelId,
        public array $reaction,
        public bool $added,
        public bool $isDm = false,
        public ?int $threadId = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->threadId) {
            return [
                new PresenceChannel('thread.'.$this->threadId),
            ];
        }

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
            'reaction' => $this->reaction,
            'added' => $this->added,
        ];
    }
}
