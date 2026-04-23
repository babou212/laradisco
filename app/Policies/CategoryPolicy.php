<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    public function create(User $user): Response
    {
        return $this->canManageChannels($user);
    }

    public function update(User $user, Category $category): Response
    {
        return $this->canManageChannels($user);
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->canManageChannels($user);
    }

    private function canManageChannels(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermissionTo('manage_channels'))
            ? Response::allow()
            : Response::deny('You do not have permission to manage categories.');
    }
}
