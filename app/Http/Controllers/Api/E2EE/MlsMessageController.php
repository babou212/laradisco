<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Concerns\AuthorizesGroupAccess;
use App\Events\MlsMessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\FetchMlsMessagesRequest;
use App\Http\Requests\Api\E2EE\SubmitMlsMessageRequest;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MlsMessage;
use App\Models\UserDevice;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $message = MlsMessage::create([
            'group_id' => $groupId,
            'sender_user_id' => $user->id,
            'sender_device_id' => $device->device_id,
            'message_type' => $validated['message_type'],
            'message_bytes' => $validated['message_bytes'],
            'epoch' => $validated['epoch'],
        ]);

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
     * Fetch history-encrypted messages for a group (for new-device history sync).
     */
    public function fetchHistory(Request $request, string $groupId): JsonResponse
    {
        $authError = $this->authorizeGroupAccess($request->user(), $groupId);
        if ($authError) {
            return $authError;
        }

        $beforeId = $request->query('before_id');
        $limit = max(1, min((int) $request->query('limit', 100), 200));

        if (preg_match('/^channel:(\d+)$/', $groupId, $m)) {
            $query = Message::where('channel_id', (int) $m[1])
                ->whereNotNull('history_ciphertext')
                ->where('history_ciphertext', '!=', '');
        } elseif (preg_match('/^dm:(\d+)$/', $groupId, $m)) {
            $query = DirectMessage::where('direct_message_group_id', (int) $m[1])
                ->whereNotNull('history_ciphertext')
                ->where('history_ciphertext', '!=', '');
        } else {
            return $this->errorResponse('Invalid group ID format.', 422);
        }

        if ($beforeId) {
            $query->where('id', '<', (int) $beforeId);
        }

        $messages = $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['id', 'history_ciphertext', 'created_at']);

        return $this->successResponse($messages);
    }
}
