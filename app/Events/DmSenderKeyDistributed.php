<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user has distributed their sender key to DM group members.
 * No cryptographic material is included — just the sender's device ID
 * so recipients know which sender key to fetch.
 */
class DmSenderKeyDistributed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public int $dmGroupId,
        public int $senderUserId,
        public string $senderDeviceId,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('direct-message.'.$this->dmGroupId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'dm_group_id' => $this->dmGroupId,
            'sender_user_id' => $this->senderUserId,
            'sender_device_id' => $this->senderDeviceId,
        ];
    }
}
