<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Concerns\AuthorizesGroupAccess;
use App\Events\MlsMessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\FetchMlsMessagesRequest;
use App\Http\Requests\Api\E2EE\SubmitMlsMessageRequest;
use App\Models\Message;
use App\Models\MlsGroup;
use App\Models\MlsMessage;
use App\Models\UserDevice;
use App\Services\PermissionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MlsMessageController extends Controller
{
    use ApiResponse, AuthorizesGroupAccess;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Submit an MLS message (commit, proposal, or application) to a group.
     */
    public function submit(SubmitMlsMessageRequest $request, string $groupId): JsonResponse
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

        // Commits must advance the group epoch by exactly one. We lock the
        // group row to serialise concurrent writers, verify the requested epoch
        // is the successor of the current epoch, then insert the commit and
        // bump current_epoch atomically. Proposals and application messages
        // pass straight through (they don't carry the epoch-advancing signal).
        if ($validated['message_type'] === 'commit') {
            try {
                $message = DB::transaction(function () use ($groupId, $user, $device, $validated) {
                    $group = MlsGroup::where('group_id', $groupId)->lockForUpdate()->first();

                    if (! $group) {
                        return ['status' => 'missing_group'];
                    }

                    $expected = $group->current_epoch + 1;
                    if ((int) $validated['epoch'] !== $expected) {
                        return [
                            'status' => 'epoch_mismatch',
                            'current_epoch' => $group->current_epoch,
                            'expected_epoch' => $expected,
                        ];
                    }

                    $created = MlsMessage::create([
                        'group_id' => $groupId,
                        'sender_user_id' => $user->id,
                        'sender_device_id' => $device->device_id,
                        'message_type' => 'commit',
                        'message_bytes' => $validated['message_bytes'],
                        'epoch' => $validated['epoch'],
                    ]);

                    $group->current_epoch = (int) $validated['epoch'];
                    $group->save();

                    return ['status' => 'ok', 'message' => $created];
                });
            } catch (QueryException $e) {
                // Unique-index violation: another request won the epoch race
                // in the exact millisecond we were committing. Re-read the
                // group and return 409 so the client can catch up and retry.
                if ($this->isUniqueViolation($e)) {
                    $group = MlsGroup::where('group_id', $groupId)->first();

                    return $this->epochConflictResponse(
                        'MLS epoch conflict: another commit won the race.',
                        [
                            'current_epoch' => $group?->current_epoch ?? 0,
                            'expected_epoch' => ($group?->current_epoch ?? 0) + 1,
                        ],
                    );
                }
                throw $e;
            }

            if (($message['status'] ?? null) === 'missing_group') {
                return $this->errorResponse('MLS group not found.', 404);
            }

            if (($message['status'] ?? null) === 'epoch_mismatch') {
                return $this->epochConflictResponse(
                    'MLS epoch conflict: submitted epoch does not match current_epoch + 1.',
                    [
                        'current_epoch' => $message['current_epoch'],
                        'expected_epoch' => $message['expected_epoch'],
                    ],
                );
            }

            $message = $message['message'];
        } else {
            $message = MlsMessage::create([
                'group_id' => $groupId,
                'sender_user_id' => $user->id,
                'sender_device_id' => $device->device_id,
                'message_type' => $validated['message_type'],
                'message_bytes' => $validated['message_bytes'],
                'epoch' => $validated['epoch'],
            ]);
        }

        try {
            broadcast(new MlsMessageReceived(
                groupId: $groupId,
                messageId: $message->id,
                senderUserId: $user->id,
                senderDeviceId: $device->device_id,
                messageType: $validated['message_type'],
                epoch: $validated['epoch'],
            ))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->createdResponse([
            'message_id' => $message->id,
            'group_id' => $groupId,
            'epoch' => $validated['epoch'],
        ], 'MLS message submitted.');
    }

    /**
     * Fetch MLS messages for a group since a given epoch or message ID.
     */
    public function fetch(FetchMlsMessagesRequest $request, string $groupId): JsonResponse
    {
        $authError = $this->authorizeGroupAccess($request->user(), $groupId);
        if ($authError) {
            return $authError;
        }

        $validated = $request->validated();

        $sinceEpoch = $validated['since_epoch'] ?? null;
        $sinceId = $validated['since_id'] ?? null;
        $limit = $validated['limit'] ?? 100;
        $messageType = $validated['message_type'] ?? null;

        $query = MlsMessage::where('group_id', $groupId);

        if ($messageType) {
            $query->where('message_type', $messageType);
        }

        if ($sinceId) {
            $query->where('id', '>', (int) $sinceId);
        } elseif ($sinceEpoch !== null) {
            $query->where('epoch', '>', (int) $sinceEpoch);
        }

        $messages = $query->orderBy('id', 'asc')
            ->limit($limit)
            ->get(['id', 'group_id', 'sender_user_id', 'sender_device_id', 'message_type', 'message_bytes', 'epoch', 'created_at']);

        return $this->successResponse($messages);
    }

    /**
     * Signal an epoch conflict to the client. The body carries both the
     * current and expected epoch so the client can run a bounded
     * catch-up + replay + retry cycle without issuing extra GET round-trips.
     */
    private function epochConflictResponse(string $message, array $meta): JsonResponse
    {
        return response()->json([
            'error' => $message,
            'code' => 'mls_epoch_conflict',
            'current_epoch' => $meta['current_epoch'],
            'expected_epoch' => $meta['expected_epoch'],
        ], 409);
    }

    /**
     * Detect Postgres unique-violation SQLSTATE (23505). We hit this when two
     * concurrent commits pass the lockForUpdate check on separate connections;
     * the partial-unique index on (group_id, epoch) catches the second.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }
}
