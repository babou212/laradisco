<?php

namespace App\Concerns;

use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

trait AuthorizesGroupAccess
{
    /**
     * Authorize that the user has access to the given MLS group.
     * Returns a JsonResponse on failure, or null if authorized.
     */
    private function authorizeGroupAccess(User $user, string $groupId): ?JsonResponse
    {
        if (preg_match('/^channel:(\d+)$/', $groupId, $m)) {
            $channel = Channel::find((int) $m[1]);
            if (! $channel) {
                return $this->errorResponse('Unknown group.', 404);
            }
            if (! $this->permissionService->userCanViewChannel($user, $channel)) {
                return $this->forbiddenResponse('You do not have access to this group.');
            }

            return null;
        }

        if (preg_match('/^dm:(\d+)$/', $groupId, $m)) {
            $dmGroup = DirectMessageGroup::find((int) $m[1]);
            if (! $dmGroup) {
                return $this->errorResponse('Unknown group.', 404);
            }
            if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
                return $this->forbiddenResponse('You do not have access to this group.');
            }

            return null;
        }

        return $this->errorResponse('Invalid group ID format.', 422);
    }
}
