<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a device needs sender key distributions from DM group members.
 * No cryptographic material is included — just the requesting device's ID.
 */
class DmSenderKeyNeeded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public int $dmGroupId,
        public int $requestingUserId,
        public string $requestingDeviceId,
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
            'requesting_user_id' => $this->requestingUserId,
            'requesting_device_id' => $this->requestingDeviceId,
        ];
    }
}
