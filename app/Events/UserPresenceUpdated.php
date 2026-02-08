<?php

namespace App\Events;

use App\Enums\UserStatusType;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The queue the broadcast event should be placed on.
     */
    public string $queue = 'broadcasting';

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public UserStatusType $status,
        public ?string $customStatus = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('online'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'status' => $this->status->value,
            'custom_status' => $this->customStatus,
        ];
    }
}
