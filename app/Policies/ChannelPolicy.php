<?php

namespace App\Policies;

use App\Enums\PermissionFlag;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    /**
     * Determine if the user can list channels in settings.
     */
    public function viewAny(User $user): Response
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine if the user can create a channel.
     */
    public function create(User $user): Response
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine if the user can update a channel.
     */
    public function update(User $user, Channel $channel): Response
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine if the user can delete a channel.
     */
    public function delete(User $user, Channel $channel): Response
    {
        return $this->canManageChannels($user);
    }

    /**
     * Determine if the user can manage permission overrides for a channel.
     */
    public function manageOverrides(User $user, Channel $channel): Response
    {
        return $this->canManageChannels($user);
    }

    private function canManageChannels(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageChannels))
            ? Response::allow()
            : Response::deny('You do not have permission to manage channels.');
    }
}
