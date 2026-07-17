<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Concerns\AuthorizesGroupAccess;
use App\Events\MlsJoinRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\FulfillJoinRequest;
use App\Http\Requests\Api\E2EE\RequestJoinRequest;
use App\Models\MlsGroup;
use App\Models\MlsJoinRequest;
use App\Models\UserDevice;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

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
                creatorUserId: (int) $existing->creator_user_id,
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
     * Mark a join request as fulfilled.
     */
    public function fulfill(FulfillJoinRequest $request, string $groupId): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $authError = $this->authorizeGroupAccess($user, $groupId);
        if ($authError) {
            return $authError;
        }

        $group = MlsGroup::where('group_id', $groupId)->first();
        if (! $group || (int) $group->creator_user_id !== (int) $user->id) {
            return $this->forbiddenResponse('Only the group creator can fulfill join requests.');
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
}
