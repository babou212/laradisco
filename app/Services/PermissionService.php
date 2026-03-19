<?php

namespace App\Services;

use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Resolve the effective permissions for a user on a specific channel.
     *
     * @return list<string>
     */
    public function resolveChannelPermissions(User $user, Channel $channel): array
    {
        $user->loadMissing('roles');

        if ($this->isAdministrator($user)) {
            return array_map(fn (PermissionFlag $p) => $p->value, PermissionFlag::cases());
        }

        $basePermissions = $user->roles
            ->flatMap(fn (Role $role) => $role->permissions ?? [])
            ->unique()
            ->values()
            ->all();

        $roleIds = $user->roles->pluck('id')->all();
        $roleOverrides = $channel->permissionOverrides()
            ->whereIn('role_id', $roleIds)
            ->whereNull('user_id')
            ->get();

        $permissions = $this->applyOverrides($basePermissions, $roleOverrides);

        $userOverrides = $channel->permissionOverrides()
            ->where('user_id', $user->id)
            ->whereNull('role_id')
            ->get();

        $permissions = $this->applyOverrides($permissions, $userOverrides);

        return $permissions;
    }

    /**
     * Check if a user has a specific permission on a channel.
     */
    public function userCanInChannel(User $user, Channel $channel, PermissionFlag $permission): bool
    {
        $cacheKey = "user.{$user->id}.channel.{$channel->id}.permissions";

        $permissions = cache()->remember($cacheKey, now()->addMinutes(15), function () use ($user, $channel) {
            return $this->resolveChannelPermissions($user, $channel);
        });

        return in_array($permission->value, $permissions, true);
    }

    /**
     * Check if a user can view a channel (considering private channels).
     *
     * @param  Collection<int, ChannelPermissionOverride>|null  $preloadedOverrides
     */
    public function userCanViewChannel(User $user, Channel $channel, ?Collection $preloadedOverrides = null): bool
    {
        if (! $channel->is_private) {
            return $this->userCanInChannel($user, $channel, PermissionFlag::ViewChannels);
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        $user->loadMissing('roles');
        $roleIds = $user->roles->pluck('id')->all();

        // Use pre-loaded overrides when available (batch path), otherwise query
        if ($preloadedOverrides !== null) {
            $channelOverrides = $preloadedOverrides->where('channel_id', $channel->id);
        } else {
            $channelOverrides = ChannelPermissionOverride::where('channel_id', $channel->id)
                ->where(function ($q) use ($roleIds, $user) {
                    $q->whereIn('role_id', $roleIds)->whereNull('user_id')
                        ->orWhere(function ($q2) use ($user) {
                            $q2->where('user_id', $user->id)->whereNull('role_id');
                        });
                })
                ->get();
        }

        $hasRoleAccess = $channelOverrides
            ->whereNull('user_id')
            ->whereIn('role_id', $roleIds)
            ->contains(fn (ChannelPermissionOverride $override) => in_array(PermissionFlag::ViewChannels->value, $override->allow ?? [], true));

        $hasUserAccess = $channelOverrides
            ->whereNull('role_id')
            ->where('user_id', $user->id)
            ->contains(fn (ChannelPermissionOverride $override) => in_array(PermissionFlag::ViewChannels->value, $override->allow ?? [], true));

        return $hasRoleAccess || $hasUserAccess;
    }

    /**
     * Get all channels a user can view, respecting permissions and overrides.
     *
     * @return Collection<int, Channel>
     */
    public function getAccessibleChannels(User $user): Collection
    {
        $cacheKey = "user.{$user->id}.accessible_channels";

        $channelIds = cache()->remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $user->loadMissing('roles');
            $channels = Channel::with('category')->get();

            $roleIds = $user->roles->pluck('id')->all();
            $allOverrides = ChannelPermissionOverride::query()
                ->where(function ($q) use ($roleIds, $user) {
                    $q->whereIn('role_id', $roleIds)->whereNull('user_id')
                        ->orWhere(function ($q2) use ($user) {
                            $q2->where('user_id', $user->id)->whereNull('role_id');
                        });
                })
                ->get();

            return $channels->filter(fn (Channel $channel) => $this->userCanViewChannel($user, $channel, $allOverrides))
                ->pluck('id')
                ->all();
        });

        return Channel::with('category')->whereIn('id', $channelIds)->get();
    }

    /**
     * Check if the user is an administrator (via any role).
     */
    public function isAdministrator(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->roles->contains(fn (Role $role) => $role->hasPermission(PermissionFlag::Administrator));
    }

    /**
     * Check if a user's highest role position is above the target user's highest role position.
     * Used for hierarchy enforcement.
     */
    public function outranks(User $actor, User $target): bool
    {
        $actor->loadMissing('roles');
        $target->loadMissing('roles');

        $actorMaxPosition = $actor->roles->max('position') ?? 0;
        $targetMaxPosition = $target->roles->max('position') ?? 0;

        return $actorMaxPosition > $targetMaxPosition;
    }

    /**
     * Check if a user can manage a specific role (based on hierarchy).
     */
    public function canManageRole(User $user, Role $role): bool
    {
        if ($this->isAdministrator($user)) {
            $user->loadMissing('roles');
            $userMaxPosition = $user->roles->max('position') ?? 0;

            return $role->position < $userMaxPosition;
        }

        return false;
    }

    /**
     * Clear all channel permission caches for a user.
     * Uses tag-based cache flushing when available, otherwise iterates known channel IDs.
     */
    public function clearUserChannelCaches(User $user): void
    {
        $channelIds = Channel::pluck('id');

        $keys = $channelIds->map(fn ($id) => "user.{$user->id}.channel.{$id}.permissions")->all();
        $keys[] = "user.{$user->id}.permissions";
        $keys[] = "user.{$user->id}.accessible_channels";

        foreach ($keys as $key) {
            cache()->forget($key);
        }
    }

    /**
     * Clear channel permission caches for all users (e.g., when a channel override changes).
     */
    public function clearChannelCaches(Channel $channel): void
    {
        User::select('id')->cursor()->each(function (User $user) use ($channel) {
            cache()->forget("user.{$user->id}.channel.{$channel->id}.permissions");
            cache()->forget("user.{$user->id}.accessible_channels");
        });
    }

    /**
     * Apply overrides (deny then allow) to a base permission set.
     *
     * @param  array<int, string>  $permissions
     * @param  Collection<int, ChannelPermissionOverride>  $overrides
     * @return list<string>
     */
    private function applyOverrides(array $permissions, Collection $overrides): array
    {
        foreach ($overrides as $override) {
            foreach ($override->deny ?? [] as $denied) {
                $permissions = array_values(array_filter($permissions, fn (string $p) => $p !== $denied));
            }

            foreach ($override->allow ?? [] as $allowed) {
                if (! in_array($allowed, $permissions, true)) {
                    $permissions[] = $allowed;
                }
            }
        }

        return array_values($permissions);
    }
}
