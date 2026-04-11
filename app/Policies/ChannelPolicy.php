<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageChannels($user);
    }

    public function create(User $user): Response
    {
        return $this->canManageChannels($user);
    }

    public function update(User $user, Channel $channel): Response
    {
        return $this->canManageChannels($user);
    }

    public function delete(User $user, Channel $channel): Response
    {
        return $this->canManageChannels($user);
    }

    public function manageOverrides(User $user, Channel $channel): Response
    {
        return $this->canManageChannels($user);
    }

    private function canManageChannels(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermissionTo('manage_channels'))
            ? Response::allow()
            : Response::deny('You do not have permission to manage channels.');
    }
}
