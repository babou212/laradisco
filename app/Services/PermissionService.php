<?php

namespace App\Services;

use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Resolve the effective permissions for a user on a specific channel.
     *
     * @param  Collection<int, ChannelPermissionOverride>|null  $preloadedOverrides
     * @return list<string>
     */
    public function resolveChannelPermissions(User $user, Channel $channel, ?Collection $preloadedOverrides = null): array
    {
        if ($this->isAdministrator($user)) {
            return array_map(fn (PermissionFlag $p) => $p->value, PermissionFlag::cases());
        }

        if ($channel->is_private && ! $this->hasPrivateChannelViewOverride($user, $channel, $preloadedOverrides)) {
            return [];
        }

        $basePermissions = $user->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        $overrides = $preloadedOverrides ?? $channel->permissionOverrides()->get();

        // Resolve in a fixed precedence order
        // so a specific role's allow reliably beats the @everyone role's deny,
        // regardless of the order overrides happen to have been created in:
        // base permissions -> @everyone override -> other roles combined -> per-user override.
        $everyoneRoleIds = $user->roles->where('is_default', true)->pluck('id');
        $otherRoleIds = $user->roles->where('is_default', false)->pluck('id');

        $roleOverrides = $overrides->whereNull('user_id');

        $permissions = $this->applyOverrides($basePermissions, $roleOverrides->whereIn('role_id', $everyoneRoleIds));
        $permissions = $this->applyOverrides($permissions, $roleOverrides->whereIn('role_id', $otherRoleIds));

        $userOverrides = $overrides
            ->where('user_id', $user->id)
            ->whereNull('role_id');

        $permissions = $this->applyOverrides($permissions, $userOverrides);

        return $permissions;
    }

    /**
     * Check if a user has a specific permission on a channel.
     *
     * @param  Collection<int, ChannelPermissionOverride>|null  $preloadedOverrides
     */
    public function userCanInChannel(User $user, Channel $channel, PermissionFlag $permission, ?Collection $preloadedOverrides = null): bool
    {
        $cacheKey = CacheKeys::userChannelPermissions($user->id, $channel->id);
        $tags = [CacheKeys::channelTag($channel->id), CacheKeys::userTag($user->id)];

        $permissions = Cache::tags($tags)->remember($cacheKey, CacheKeys::TTL_LONG, function () use ($user, $channel, $preloadedOverrides) {
            return $this->resolveChannelPermissions($user, $channel, $preloadedOverrides);
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
        // Preloaded overrides may span multiple channels (e.g. getAccessibleChannels), so scope
        // them down to this channel before reusing them in either branch below.
        $channelOverrides = $preloadedOverrides?->where('channel_id', $channel->id)->values();

        if (! $channel->is_private) {
            return $this->userCanInChannel($user, $channel, PermissionFlag::ViewChannels, $channelOverrides);
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        return $this->hasPrivateChannelViewOverride($user, $channel, $channelOverrides);
    }

    /**
     * Check if a user has an explicit role or user override granting view_channels
     * on a private channel. Private channels ignore base/role-wide permissions for
     * visibility (and, by extension, every other channel permission) — access must
     * come from an override.
     *
     * @param  Collection<int, ChannelPermissionOverride>|null  $preloadedOverrides
     */
    private function hasPrivateChannelViewOverride(User $user, Channel $channel, ?Collection $preloadedOverrides = null): bool
    {
        $roleIds = $user->roles->pluck('id')->all();

        $channelOverrides = $preloadedOverrides ?? ChannelPermissionOverride::where('channel_id', $channel->id)
            ->where(function ($q) use ($roleIds, $user) {
                $q->whereIn('role_id', $roleIds)->whereNull('user_id')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)->whereNull('role_id');
                    });
            })
            ->get();

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
        $cacheKey = CacheKeys::userAccessibleChannels($user->id);
        $tags = [CacheKeys::userTag($user->id), CacheKeys::TAG_SIDEBAR];

        $channelIds = Cache::tags($tags)->remember($cacheKey, CacheKeys::TTL_WARM, function () use ($user) {
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
     * Get IDs of all users who can view the given channel.
     *
     * @return list<int>
     */
    public function getUsersWithChannelAccess(Channel $channel): array
    {
        $cacheKey = CacheKeys::channelViewers($channel->id);
        $tags = [CacheKeys::channelTag($channel->id)];

        /** @var list<int> */
        return Cache::tags($tags)->remember($cacheKey, CacheKeys::TTL_WARM, function () use ($channel) {
            $channelOverrides = $channel->permissionOverrides()->get();

            return User::query()
                ->without('media')
                ->select('id')
                ->with(['roles.permissions', 'permissions'])
                ->get()
                ->filter(fn (User $user) => $this->userCanViewChannel($user, $channel, $channelOverrides))
                ->pluck('id')
                ->values()
                ->all();
        });
    }

    /**
     * Check if the user is an administrator (via Spatie).
     */
    public function isAdministrator(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Check if a user's highest role position is above the target user's highest role position.
     */
    public function outranks(User $actor, User $target): bool
    {
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
            $userMaxPosition = $user->roles->max('position') ?? 0;

            return $role->position < $userMaxPosition;
        }

        return false;
    }

    /**
     * Clear all channel permission caches for a user.
     */
    public function clearUserChannelCaches(User $user): void
    {
        Cache::tags([CacheKeys::userTag($user->id)])->flush();
    }

    /**
     * Clear channel permission caches for all users.
     */
    public function clearChannelCaches(Channel $channel): void
    {
        Cache::tags([CacheKeys::channelTag($channel->id)])->flush();
    }

    /**
     * Apply a set of overrides to a base permission set: every deny across the
     * whole set is applied first, then every allow. Batching it this way (rather
     * than looping deny-then-allow per override) makes the result independent of
     * the overrides' iteration order — when a set spans multiple roles, any one
     * role's allow always wins over another role's deny for the same permission.
     *
     * @param  array<int, string>  $permissions
     * @param  Collection<int, ChannelPermissionOverride>  $overrides
     * @return list<string>
     */
    private function applyOverrides(array $permissions, Collection $overrides): array
    {
        $denies = $overrides->flatMap(fn (ChannelPermissionOverride $o) => $o->deny ?? [])->unique()->all();
        $allows = $overrides->flatMap(fn (ChannelPermissionOverride $o) => $o->allow ?? [])->unique()->all();

        $permissions = array_values(array_diff($permissions, $denies));

        foreach ($allows as $allowed) {
            if (! in_array($allowed, $permissions, true)) {
                $permissions[] = $allowed;
            }
        }

        return array_values($permissions);
    }
}
