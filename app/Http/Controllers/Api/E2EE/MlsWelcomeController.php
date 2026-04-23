<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Concerns\AuthorizesGroupAccess;
use App\Events\MlsWelcomeReady;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\SubmitWelcomeRequest;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\MlsWelcomeMessage;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MlsWelcomeController extends Controller
{
    use ApiResponse, AuthorizesGroupAccess;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Submit Welcome + RatchetTree for specific recipients.
     */
    public function submit(SubmitWelcomeRequest $request, string $groupId): JsonResponse
    {
        $authError = $this->authorizeGroupAccess($request->user(), $groupId);
        if ($authError) {
            return $authError;
        }

        if ($request->has('recipients')) {
            $recipients = $request->input('recipients');
        } else {
            $recipients = [[
                'user_id' => $request->input('recipient_user_id'),
                'device_id' => $request->input('recipient_device_id'),
                'welcome_bytes' => $request->input('welcome_bytes'),
            ]];
        }

        $ratchetTreeBytes = $request->input('ratchet_tree_bytes');

        $channel = null;
        $dmGroup = null;

        if (preg_match('/^channel:(\d+)$/', $groupId, $m)) {
            $channel = Channel::find((int) $m[1]);
        } elseif (preg_match('/^dm:(\d+)$/', $groupId, $m)) {
            $dmGroup = DirectMessageGroup::find((int) $m[1]);
        }

        if (! $channel && ! $dmGroup) {
            return $this->errorResponse('Unable to resolve group for recipient validation.', 422);
        }

        foreach ($recipients as $index => $recipient) {
            $deviceExists = UserDevice::where('user_id', $recipient['user_id'])
                ->where('device_id', $recipient['device_id'])
                ->where('is_active', true)
                ->exists();

            if (! $deviceExists) {
                return $this->errorResponse(
                    "Recipient at index {$index}: device does not belong to the specified user or is inactive.",
                    422
                );
            }

            $recipientUser = User::find($recipient['user_id']);

            if (! $recipientUser instanceof User) {
                return $this->errorResponse(
                    "Recipient at index {$index}: user not found.",
                    422
                );
            }

            if ($channel) {
                if (! $this->permissionService->userCanViewChannel($recipientUser, $channel)) {
                    return $this->errorResponse(
                        "Recipient at index {$index}: user does not have access to this group.",
                        403
                    );
                }
            } elseif ($dmGroup) {
                if (! $dmGroup->participants()->where('users.id', $recipient['user_id'])->exists()) {
                    return $this->errorResponse(
                        "Recipient at index {$index}: user does not have access to this group.",
                        403
                    );
                }
            }
        }

        foreach ($recipients as $recipient) {
            MlsWelcomeMessage::create([
                'group_id' => $groupId,
                'recipient_user_id' => $recipient['user_id'],
                'recipient_device_id' => $recipient['device_id'],
                'welcome_bytes' => $recipient['welcome_bytes'],
                'ratchet_tree_bytes' => $ratchetTreeBytes,
            ]);

            try {
                broadcast(new MlsWelcomeReady(
                    recipientUserId: $recipient['user_id'],
                    recipientDeviceId: $recipient['device_id'],
                    groupId: $groupId,
                ))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->createdResponse([
            'group_id' => $groupId,
            'recipients_count' => count($recipients),
        ], 'Welcome messages submitted.');
    }

    /**
     * Fetch pending Welcome messages for the authenticated user's device.
     */
    public function fetch(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceId = $request->query('device_id')
            ?? $request->header('X-Device-Id');

        if (empty($deviceId)) {
            return $this->errorResponse('A device_id query parameter or X-Device-Id header is required.', 422);
        }

        $deviceExists = UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->exists();

        if (! $deviceExists) {
            return $this->forbiddenResponse('Invalid or inactive device.');
        }

        return DB::transaction(function () use ($user, $deviceId) {
            $welcomes = MlsWelcomeMessage::where('recipient_user_id', $user->id)
                ->where('recipient_device_id', $deviceId)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get([
                    'id', 'group_id', 'recipient_device_id', 'welcome_bytes', 'ratchet_tree_bytes', 'created_at',
                ]);

            if ($welcomes->isNotEmpty()) {
                MlsWelcomeMessage::whereIn('id', $welcomes->pluck('id'))
                    ->update(['consumed_at' => now()]);
            }

            return $this->successResponse($welcomes);
        });
    }
}
