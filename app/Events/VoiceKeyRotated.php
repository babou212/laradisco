<?php

namespace App\Events;

use App\Models\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a rotated E2EE key to the remaining members of a voice channel.
 *
 * Broadcasts on the private `voice.channel.{id}` channel, whose authorization
 * requires the Connect permission — so a member who has just left (and lost
 * Connect) does not receive the new key. Each media frame carries its key index,
 * so remaining members switch their encrypt key to the new index while still
 * decrypting in-flight frames under the previous index.
 */
class VoiceKeyRotated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Channel $channel,
        public string $key,
        public int $keyIndex,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice.channel.'.$this->channel->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voice.key_rotated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channel->id,
            'e2ee_key' => $this->key,
            'e2ee_key_index' => $this->keyIndex,
        ];
    }
}
