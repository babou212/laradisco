<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an MLS message (commit, proposal, or application) is posted
 * to a group. Tells other clients to fetch and process the message.
 */
class MlsMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(
        public string $groupId,
        public int $messageId,
        public int $senderUserId,
        public string $senderDeviceId,
        public string $messageType,
        public int $epoch,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if (str_starts_with($this->groupId, 'channel:')) {
            $channelId = (int) substr($this->groupId, 8);

            return [
                new PresenceChannel('channel.'.$channelId),
            ];
        }

        if (str_starts_with($this->groupId, 'dm:')) {
            $dmGroupId = (int) substr($this->groupId, 3);

            return [
                new PresenceChannel('direct-message.'.$dmGroupId),
            ];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'group_id' => $this->groupId,
            'sender_user_id' => $this->senderUserId,
            'sender_device_id' => $this->senderDeviceId,
            'message_type' => $this->messageType,
            'epoch' => $this->epoch,
        ];
    }
}
