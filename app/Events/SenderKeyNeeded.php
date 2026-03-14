<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a device needs sender key distributions from channel members.
 *
 * This event carries NO cryptographic material — it simply notifies
 * other online clients that a device needs their sender keys redistributed.
 * Each recipient client then encrypts and uploads their sender key
 * distribution for the requesting device.
 */
class SenderKeyNeeded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public int $channelId,
        public int $requestingUserId,
        public string $requestingDeviceId,
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
            'user_id' => $this->requestingUserId,
            'device_id' => $this->requestingDeviceId,
        ];
    }
}
