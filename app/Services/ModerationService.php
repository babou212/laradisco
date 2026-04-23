<?php

namespace App\Services;

use App\Models\Ban;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class ModerationService
{
    /**
     * Ban a user from the server.
     */
    public function ban(User $target, User $actor, ?string $reason = null, ?\DateTimeInterface $expiresAt = null): Ban
    {
        return Ban::create([
            'user_id' => $target->id,
            'banned_by' => $actor->id,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);
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
