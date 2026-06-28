<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserBanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public ?string $reason = null,
        public ?\DateTimeInterface $expiresAt = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts on the banned user's own private channel — the same channel the
     * client already subscribes to for notifications — so every connected device
     * is told to log out immediately. Published via Reverb with app credentials,
     * independent of the user's (about-to-be-revoked) API token.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->user->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.banned';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'permanent' => $this->expiresAt === null,
        ];
    }
}
