<?php

namespace App\Events;

use App\Models\Role;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRolesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public User $user) {}

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
        return 'user.roles.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $roles = $this->user->roles
            ->sortByDesc('position')
            ->values()
            ->map(fn (Role $role): array => [
                'id' => (string) $role->id,
                'name' => $role->name,
                'color' => $role->color,
                'position' => $role->position,
                'is_hoisted' => $role->is_hoisted,
                'is_default' => $role->is_default,
                'is_mentionable' => $role->is_mentionable,
            ])
            ->all();

        return [
            'user_id' => $this->user->id,
            'roles' => $roles,
            'permissions' => $this->user->permissionsPayload(),
        ];
    }
}
