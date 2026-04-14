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
    }
}
