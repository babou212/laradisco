<?php

namespace App\Policies;

use App\Enums\PermissionFlag;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /**
     * Determine if the user can create a category.
     */
    public function create(User $user): Response
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine if the user can update a category.
     */
    public function update(User $user, Category $category): Response
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine if the user can delete a category.
     */
    public function delete(User $user, Category $category): Response
    {
        return $this->canManageChannels($user);
    }

    private function canManageChannels(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageChannels))
            ? Response::allow()
            : Response::deny('You do not have permission to manage categories.');
    }
}
