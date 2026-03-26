<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to all devices of the group creator (and other existing members)
 * when a user/device requests to join an MLS group. The receiving device
 * should add the requester and send them a Welcome message.
 */
class MlsJoinRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public int $creatorUserId,
        public string $groupId,
        public int $requesterUserId,
        public string $requesterDeviceId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->creatorUserId),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'requester_user_id' => $this->requesterUserId,
            'requester_device_id' => $this->requesterDeviceId,
        ];
    }
}
