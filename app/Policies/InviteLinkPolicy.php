<?php

namespace App\Policies;

use App\Enums\PermissionFlag;
use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InviteLinkPolicy
{
    /**
     * Determine if the user can list invite links.
     */
    public function viewAny(User $user): Response
    {
        return $this->canManageInvites($user);
    }

    /**
     * Determine if the user can create an invite link.
     */
    public function create(User $user): Response
    {
        return $this->canManageInvites($user);
    }

    /**
     * Determine if the user can delete an invite link.
     */
    public function delete(User $user, InviteLink $inviteLink): Response
    {
        if ($inviteLink->used_at !== null) {
            return Response::deny('Cannot delete a used invite link.');
        }

        return $this->canManageInvites($user);
    }

    private function canManageInvites(User $user): Response
    {
        return ($user->isAdministrator() || $user->hasPermission(PermissionFlag::InviteMembers))
            ? Response::allow()
            : Response::deny('You do not have permission to manage invite links.');
    }
}
