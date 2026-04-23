<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageRoles($user);
    }

    public function create(User $user): Response
    {
        return $this->canManageRoles($user);
    }

    public function update(User $user, Role $role): Response
    {
        return $this->canManageRoles($user);
    }

    public function delete(User $user, Role $role): Response
    {
        if ($role->is_default) {
            return Response::deny('Cannot delete the default role.');
        }

        return $this->canManageRoles($user);
    }

    private function canManageRoles(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermissionTo('manage_roles'))
            ? Response::allow()
            : Response::deny('You do not have permission to manage roles.');
    }
}
