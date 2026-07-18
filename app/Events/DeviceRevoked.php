<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to a user's private channel when a device is revoked.
 * The revoked device wipes its local keys and logs out; sibling devices
 * reconcile MLS group membership to evict it.
 */
class DeviceRevoked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public int $userId,
        public string $deviceId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'device.revoked';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'device_id' => $this->deviceId,
        ];
    }
}
