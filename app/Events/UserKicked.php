<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user is kicked: removed from the server (roles stripped,
 * tokens revoked) but — unlike a ban or a permanent delete — with no barrier
 * to coming back once an admin re-assigns them a role.
 *
 * Broadcasts on two channels: the kicked user's own private channel (so their
 * own device logs out immediately, same mechanism as a ban) and the shared
 * `presence` channel (so every other connected client drops them from the
 * member list — without the "(deleted)" tombstone treatment a real account
 * deletion gets, since the account itself still exists).
 */
class UserKicked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->user->id),
            new PrivateChannel('presence'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.kicked';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
        ];
    }
}
