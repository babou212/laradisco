<?php

namespace App\Policies;

use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InviteLinkPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->canManageInvites($user);
    }

    public function create(User $user): Response
    {
        return $this->canManageInvites($user);
    }

    public function delete(User $user, InviteLink $inviteLink): Response
    {
        if ($inviteLink->used_at !== null) {
            return Response::deny('Cannot delete a used invite link.');
        }

        return $this->canManageInvites($user);
    }

    private function canManageInvites(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermissionTo('invite_members'))
            ? Response::allow()
            : Response::deny('You do not have permission to manage invite links.');
    }
}
