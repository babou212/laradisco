<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user has distributed (or redistributed) their sender key
 * to channel members. This tells other clients to re-fetch sender keys
 * so they can decrypt messages from this sender.
 *
 * No cryptographic material is included — just the sender's device ID
 * so recipients know which sender key to fetch.
 */
class SenderKeyDistributed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public int $channelId,
        public int $senderUserId,
        public string $senderDeviceId,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('channel.'.$this->channelId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->senderUserId,
            'device_id' => $this->senderDeviceId,
        ];
    }
}
