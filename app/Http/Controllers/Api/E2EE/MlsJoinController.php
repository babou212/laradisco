<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Concerns\AuthorizesGroupAccess;
use App\Events\MlsJoinRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\FulfillJoinRequest;
use App\Http\Requests\Api\E2EE\RequestJoinRequest;
use App\Models\DirectMessageGroup;
use App\Models\MlsGroup;
use App\Models\MlsJoinRequest;
use App\Models\UserDevice;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MlsJoinController extends Controller
{
    use ApiResponse, AuthorizesGroupAccess;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Request to be added to an existing MLS group.
     */
    public function requestJoin(RequestJoinRequest $request, string $groupId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $authError = $this->authorizeGroupAccess($user, $groupId);
        if ($authError) {
            return $authError;
        }

        $deviceId = $validated['device_id']
            ?? $request->header('X-Device-Id');

        if (empty($deviceId)) {
            return $this->errorResponse('A device_id field or X-Device-Id header is required.', 422);
        }

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->forbiddenResponse('Invalid or inactive device.');
        }

        $existing = MlsGroup::where('group_id', $groupId)->first();

        if (! $existing) {
            return $this->errorResponse('Group does not exist. Use claim to create it.', 404);
        }

        $joinRequest = MlsJoinRequest::updateOrCreate(
            ['group_id' => $groupId, 'device_id' => $deviceId],
            ['user_id' => $user->id, 'status' => 'pending'],
        );

        try {
            broadcast(new MlsJoinRequested(
                recipientUserIds: $this->groupParticipantIds($groupId, (int) $existing->creator_user_id),
                groupId: $groupId,
                requesterUserId: $user->id,
                requesterDeviceId: $deviceId,
            ))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->createdResponse([
            'group_id' => $groupId,
            'status' => 'pending',
            'join_request_id' => $joinRequest->id,
        ], 'Join request submitted.');
    }

    /**
     * List pending join requests for a group, so member devices can heal
     * requesters that missed the broadcast (e.g. the member was offline).
     */
    public function pending(Request $request, string $groupId): JsonResponse
    {
        $authError = $this->authorizeGroupAccess($request->user(), $groupId);
        if ($authError) {
            return $authError;
        }

        $requests = MlsJoinRequest::where('group_id', $groupId)
            ->where('status', 'pending')
            ->get(['group_id', 'user_id', 'device_id']);

        return $this->successResponse($requests);
    }

    /**
     * Mark a join request as fulfilled. Any authorized group member may
     * fulfill — healing a wedged device must not depend on the group creator
     * being online.
     */
    public function fulfill(FulfillJoinRequest $request, string $groupId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $authError = $this->authorizeGroupAccess($user, $groupId);
        if ($authError) {
            return $authError;
        }

        $updated = MlsJoinRequest::where('group_id', $groupId)
            ->where('device_id', $validated['device_id'])
            ->where('status', 'pending')
            ->update(['status' => 'fulfilled']);

        if ($updated === 0) {
            return $this->errorResponse('No pending join request found.', 404);
        }

        return $this->successResponse(['group_id' => $groupId], 'Join request fulfilled.');
    }

    /**
     * User ids that should hear about a join request: all DM participants, or
     * the creator as a fallback for other group kinds.
     *
     * @return list<int>
     */
    private function groupParticipantIds(string $groupId, int $creatorUserId): array
    {
        if (preg_match('/^dm:(\d+)$/', $groupId, $m)) {
            $dmGroup = DirectMessageGroup::find((int) $m[1]);
            if ($dmGroup) {
                return $dmGroup->participants()->pluck('users.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }

        return [$creatorUserId];
    }
}
