<?php

namespace App\Services;

use App\Models\Ban;
use App\Models\Role;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class ModerationService
{
    /**
     * Ban a user from the server.
     */
    public function ban(User $target, User $actor, ?string $reason = null, ?\DateTimeInterface $expiresAt = null): Ban
    {
        $ban = Ban::create([
            'user_id' => $target->id,
            'banned_by' => $actor->id,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);

        $this->flushUserCaches($target);

        return $ban;
    }

    /**
     * Unban a user (delete all active bans).
     */
    public function unban(User $target): void
    {
        $target->bans()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->delete();

        $this->flushUserCaches($target);
    }

    /**
     * Invalidate the target user's cached state after a moderation action.
     *
     * Unban uses a mass delete, which does not fire model events, so the cached
     * ban status (and the rest of the user's permission caches, whose semantics
     * change with a ban) is flushed explicitly here for both ban and unban.
     */
    protected function flushUserCaches(User $target): void
    {
        Cache::tags([CacheKeys::userTag($target->id)])->flush();
    }

    /**
     * Jail a user — assign the Jailed role and remove all other roles.
     */
    public function jail(User $target): void
    {
        $jailedRole = Role::where('name', 'Jailed')->first();

        if (! $jailedRole) {
            return;
        }

        $target->syncRoles([$jailedRole]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Unjail a user — remove Jailed role and assign default everyone role.
     */
    public function unjail(User $target): void
    {
        $jailedRole = Role::where('name', 'Jailed')->first();
        $defaultRole = Role::where('is_default', true)->first();

        if ($jailedRole) {
            $target->removeRole($jailedRole);
        }

        if ($defaultRole && ! $target->hasRole($defaultRole)) {
            $target->assignRole($defaultRole);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
