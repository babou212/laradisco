<?php

namespace App\Policies;

use App\Enums\PermissionFlag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    /**
     * Determine if the user can list roles.
     */
    public function viewAny(User $user): Response
    {
        return $this->canManageRoles($user);
    }

    /**
     * Determine if the user can create a role.
     */
    public function create(User $user): Response
    {
        return $this->canManageRoles($user);
    }

    /**
     * Determine if the user can update a role.
     */
    public function update(User $user, Role $role): Response
    {
        return $this->canManageRoles($user);
    }

    /**
     * Determine if the user can delete a role.
     */
    public function delete(User $user, Role $role): Response
    {
        if ($role->is_default) {
            return Response::deny('Cannot delete the default role.');
        }

        return $this->canManageRoles($user);
    }

    private function canManageRoles(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageRoles))
            ? Response::allow()
            : Response::deny('You do not have permission to manage roles.');
    }
}
