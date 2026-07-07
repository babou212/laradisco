<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals that a channel, category, or channel permission override changed in
 * a way that could affect who can see which channels. Carries no payload —
 * listeners just refetch their channel list, since visibility is resolved
 * per-user server-side.
 */
class ChannelUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('presence'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'channel.updated';
    }
}
