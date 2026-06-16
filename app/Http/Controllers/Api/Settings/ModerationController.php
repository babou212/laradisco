<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Events\UserDeleted;
use App\Http\Controllers\Controller;
use App\Models\Ban;
use App\Models\User;
use App\Services\ModerationAuditService;
use App\Services\ModerationService;
use App\Services\PermissionService;
use App\Services\UserDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModerationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ModerationService $moderationService,
        private readonly PermissionService $permissionService,
        private readonly ModerationAuditService $auditService,
        private readonly UserDeletionService $userDeletionService,
    ) {}

    /**
     * List all active bans.
     */
    public function bans(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('ban_members') && ! $user->isAdministrator()) {
            return $this->forbiddenResponse('You do not have permission to view bans.');
        }

        $bans = Ban::with(['user:id,username', 'bannedBy:id,username'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();

        // Rename 'banned_by' relationship to 'banned_by_user' for frontend consistency
        $bans->each(function (Ban $ban) {
            $ban->setRelation('banned_by_user', $ban->bannedBy);
            $ban->unsetRelation('bannedBy');
        });

        return response()->json(['data' => $bans]);
    }

    /**
     * Ban a user.
     */
    public function ban(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->hasPermissionTo('ban_members') && ! $actor->isAdministrator()) {
            return $this->forbiddenResponse('You do not have permission to ban members.');
        }

        if ($user->id === $actor->id) {
            return $this->forbiddenResponse('You cannot ban yourself.');
        }

        if (! $this->permissionService->outranks($actor, $user)) {
            return $this->forbiddenResponse('You cannot ban a user with a higher or equal role.');
        }

        if ($user->isBanned()) {
            return $this->forbiddenResponse('This user is already banned.');
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $ban = $this->moderationService->ban(
            target: $user,
            actor: $actor,
            reason: $request->input('reason'),
            expiresAt: $request->input('expires_at') ? new \DateTime($request->input('expires_at')) : null,
        );

        $ban->load(['user:id,username', 'bannedBy:id,username']);

        $this->auditService->log(
            actorId: $actor->id,
            action: ModerationAction::Ban,
            targetUserId: $user->id,
            metadata: array_filter([
                'target_username' => $user->username,
                'reason' => $request->input('reason'),
                'expires_at' => $request->input('expires_at'),
            ]),
        );

        return $this->createdResponse($ban);
    }

    /**
     * Unban a user.
     */
    public function unban(Request $request, User $user): JsonResponse|Response
    {
        $actor = $request->user();

        if (! $actor->hasPermissionTo('ban_members') && ! $actor->isAdministrator()) {
            return $this->forbiddenResponse('You do not have permission to unban members.');
        }

        if (! $user->isBanned()) {
            return $this->forbiddenResponse('This user is not banned.');
        }

        $this->moderationService->unban($user);

        $this->auditService->log(
            actorId: $actor->id,
            action: ModerationAction::Unban,
            targetUserId: $user->id,
            metadata: ['target_username' => $user->username],
        );

        return $this->successResponse(message: 'User has been unbanned.');
    }

    /**
     * Permanently delete a user from the server.
     *
     * The account is removed entirely, but the user's messages are retained and
     * relabelled as authored by a deleted user. This is a stronger, admin-only
     * alternative to banning.
     */
    public function deleteUser(Request $request, User $user): JsonResponse|Response
    {
        $actor = $request->user();

        if (! $actor->isAdministrator() && ! $actor->hasPermissionTo('manage_server')) {
            return $this->forbiddenResponse('You do not have permission to delete members.');
        }

        if ($user->id === $actor->id) {
            return $this->forbiddenResponse('You cannot delete your own account from here.');
        }

        if (! $this->permissionService->outranks($actor, $user)) {
            return $this->forbiddenResponse('You cannot delete a user with a higher or equal role.');
        }

        $userId = $user->id;
        $username = $user->username;

        // Logged before deletion so the audit row's target_user_id FK is valid;
        // it nulls out on delete, but the username is retained in metadata.
        $this->auditService->log(
            actorId: $actor->id,
            action: ModerationAction::DeleteUser,
            targetUserId: $userId,
            metadata: ['target_username' => $username],
        );

        $this->userDeletionService->delete($user);

        UserDeleted::dispatch($userId, $username);

        return $this->successResponse(message: 'User has been permanently deleted.');
    }

    /**
     * Jail a user (restrict to jail channels only).
     */
    public function jail(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->hasPermissionTo('kick_members') && ! $actor->isAdministrator()) {
            return $this->forbiddenResponse('You do not have permission to jail members.');
        }

        if ($user->id === $actor->id) {
            return $this->forbiddenResponse('You cannot jail yourself.');
        }

        if (! $this->permissionService->outranks($actor, $user)) {
            return $this->forbiddenResponse('You cannot jail a user with a higher or equal role.');
        }

        if ($user->isJailed()) {
            return $this->forbiddenResponse('This user is already jailed.');
        }

        $this->moderationService->jail($user);

        $this->auditService->log(
            actorId: $actor->id,
            action: ModerationAction::Jail,
            targetUserId: $user->id,
            metadata: ['target_username' => $user->username],
        );

        return $this->successResponse(message: 'User has been jailed.');
    }

    /**
     * Unjail a user.
     */
    public function unjail(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->hasPermissionTo('kick_members') && ! $actor->isAdministrator()) {
            return $this->forbiddenResponse('You do not have permission to unjail members.');
        }

        if (! $user->isJailed()) {
            return $this->forbiddenResponse('This user is not jailed.');
        }

        $this->moderationService->unjail($user);

        $this->auditService->log(
            actorId: $actor->id,
            action: ModerationAction::Unjail,
            targetUserId: $user->id,
            metadata: ['target_username' => $user->username],
        );

        return $this->successResponse(message: 'User has been unjailed.');
    }
}
