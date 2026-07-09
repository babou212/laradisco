<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-members', function (User $user): bool {
            return $user->isAdministrator() || $user->hasPermissionTo('manage_roles');
        });

        // Broader than manage-members: covers everyone who legitimately needs to see
        // the member list (role assignment, moderation actions, channel permission
        // overrides) without granting the ability to assign/remove roles itself.
        Gate::define('view-members', function (User $user): bool {
            return $user->isAdministrator()
                || $user->hasPermissionTo('manage_roles')
                || $user->hasPermissionTo('manage_channels')
                || $user->hasPermissionTo('kick_members')
                || $user->hasPermissionTo('ban_members');
        });
    }
}
