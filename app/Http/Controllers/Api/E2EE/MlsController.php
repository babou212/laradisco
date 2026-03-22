<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Events\MlsMessageReceived;
use App\Events\MlsWelcomeReady;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\MlsKeyPackage;
use App\Models\MlsMessage;
use App\Models\MlsWelcomeMessage;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MlsController extends Controller
{
    use ApiResponse;

    /**
     * Upload key packages for the authenticated user's device.
     */
    public function uploadKeyPackages(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => ['sometimes', 'string', 'uuid'],
            'key_packages' => ['required', 'array', 'min:1', 'max:100'],
            'key_packages.*.key_package_bytes' => ['required', 'string'],
            'key_packages.*.key_package_hash' => ['required', 'string', 'size:64'],
        ]);

        $user = $request->user();
        $validated = $request->all();

        $deviceId = $validated['device_id']
            ?? $request->header('X-Device-Id');

        $deviceQuery = UserDevice::where('user_id', $user->id)
            ->where('is_active', true);

        if (! empty($deviceId)) {
            $deviceQuery->where('device_id', $deviceId);
        } else {
            $deviceQuery->latest('updated_at');
        }

        $device = $deviceQuery->first();

        if (! $device) {
            return $this->forbiddenResponse('Invalid or inactive device.');
        }

        $validated['device_id'] = $device->device_id;

        $created = 0;
        foreach ($validated['key_packages'] as $kp) {
            $exists = MlsKeyPackage::where('key_package_hash', $kp['key_package_hash'])->exists();
            if (! $exists) {
                MlsKeyPackage::create([
                    'user_id' => $user->id,
                    'device_id' => $validated['device_id'],
                    'key_package_bytes' => $kp['key_package_bytes'],
                    'key_package_hash' => $kp['key_package_hash'],
                ]);
                $created++;
            }
        }

        return $this->createdResponse([
            'uploaded' => $created,
        ], 'Key packages uploaded.');
    }

    /**
     * Fetch one available key package per device for a given user.
     * Marks consumed key packages so they aren't reused.
     */
    public function fetchKeyPackages(Request $request, User $user): JsonResponse
    {
        $deviceId = $request->query('device_id');

        return DB::transaction(function () use ($user, $deviceId) {
            $query = UserDevice::where('user_id', $user->id)
                ->where('is_active', true);

            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }

            $devices = $query->get();

            $packages = [];
            foreach ($devices as $device) {
                $kp = MlsKeyPackage::where('user_id', $user->id)
                    ->where('device_id', $device->device_id)
                    ->whereNull('consumed_at')
                    ->lockForUpdate()
                    ->first();

                if ($kp) {
                    $kp->update(['consumed_at' => now()]);
                    $packages[] = [
                        'device_id' => $device->device_id,
                        'key_package_bytes' => $kp->key_package_bytes,
                        'key_package_hash' => $kp->key_package_hash,
                    ];
                }
            }

            return $this->successResponse($packages);
        });
    }

    /**
     * Check remaining available key package count for the authenticated user's device.
     */
    public function keyPackageCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceId = $request->query('device_id');

        $query = MlsKeyPackage::where('user_id', $user->id)
            ->whereNull('consumed_at');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        return $this->successResponse([
            'count' => $query->count(),
        ]);
    }

    /**
     * Submit an MLS message (commit, proposal, or application) to a group.
     * Stores it for catch-up and broadcasts to group members.
     */
    public function submitMessage(Request $request, string $groupId): JsonResponse
    {
        $request->validate([
            'device_id' => ['sometimes', 'string', 'uuid'],
            'message_type' => ['required', 'string', 'in:commit,proposal,application'],
            'message_bytes' => ['required', 'string'],
            'epoch' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $validated = $request->all();

        $deviceId = $validated['device_id']
            ?? $request->header('X-Device-Id');

        $deviceQuery = UserDevice::where('user_id', $user->id)
            ->where('is_active', true);

        if (! empty($deviceId)) {
            $deviceQuery->where('device_id', $deviceId);
        } else {
            $deviceQuery->latest('updated_at');
        }

        $device = $deviceQuery->first();

        if (! $device) {
            return $this->forbiddenResponse('Invalid or inactive device.');
        }

        $validated['device_id'] = $device->device_id;

        $message = MlsMessage::create([
            'group_id' => $groupId,
            'sender_user_id' => $user->id,
            'sender_device_id' => $validated['device_id'],
            'message_type' => $validated['message_type'],
            'message_bytes' => $validated['message_bytes'],
            'epoch' => $validated['epoch'],
        ]);

        try {
            broadcast(new MlsMessageReceived(
                groupId: $groupId,
                messageId: $message->id,
                senderUserId: $user->id,
                senderDeviceId: $validated['device_id'],
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
     * Used for catch-up after being offline.
     */
    public function fetchMessages(Request $request, string $groupId): JsonResponse
    {
        $sinceEpoch = $request->query('since_epoch');
        $sinceId = $request->query('since_id');
        $limit = max(1, min((int) $request->query('limit', 100), 500));

        $messageType = $request->query('message_type');

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
     * Submit Welcome + RatchetTree for specific recipients.
     * Called after adding members to a group.
     */
    public function submitWelcome(Request $request, string $groupId): JsonResponse
    {
        // Accept both single-recipient and multi-recipient formats
        if ($request->has('recipients')) {
            $request->validate([
                'recipients' => ['required', 'array', 'min:1'],
                'recipients.*.user_id' => ['required', 'integer', 'exists:users,id'],
                'recipients.*.device_id' => ['required', 'string', 'uuid'],
                'recipients.*.welcome_bytes' => ['required', 'string'],
                'ratchet_tree_bytes' => ['required', 'string'],
            ]);
            $recipients = $request->input('recipients');
        } else {
            $request->validate([
                'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
                'recipient_device_id' => ['required', 'string', 'uuid'],
                'welcome_bytes' => ['required', 'string'],
                'ratchet_tree_bytes' => ['required', 'string'],
            ]);
            $recipients = [[
                'user_id' => $request->input('recipient_user_id'),
                'device_id' => $request->input('recipient_device_id'),
                'welcome_bytes' => $request->input('welcome_bytes'),
            ]];
        }

        $ratchetTreeBytes = $request->input('ratchet_tree_bytes');

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
    public function fetchWelcomes(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceId = $request->query('device_id');

        $query = MlsWelcomeMessage::where('recipient_user_id', $user->id)
            ->whereNull('consumed_at');

        if ($deviceId) {
            $query->where('recipient_device_id', $deviceId);
        }

        $welcomes = $query->get([
            'id', 'group_id', 'recipient_device_id', 'welcome_bytes', 'ratchet_tree_bytes', 'created_at',
        ]);

        if ($welcomes->isNotEmpty()) {
            MlsWelcomeMessage::whereIn('id', $welcomes->pluck('id'))
                ->update(['consumed_at' => now()]);
        }

        return $this->successResponse($welcomes);
    }

    /**
     * Get member device bundles for a channel (all users with active devices).
     */
    public function channelMemberBundles(Request $request, Channel $channel): JsonResponse
    {
        $users = User::whereHas('devices', fn ($q) => $q->where('is_active', true))
            ->with(['devices' => fn ($q) => $q->where('is_active', true)->select('user_id', 'device_id')])
            ->get(['id']);

        $result = $users->map(fn ($user) => [
            'user_id' => $user->id,
            'devices' => $user->devices->map(fn ($d) => ['device_id' => $d->device_id])->values(),
        ]);

        return $this->successResponse($result);
    }

    /**
     * Get member device bundles for a DM group (participants with active devices).
     */
    public function dmMemberBundles(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $users = $dmGroup->participants()
            ->whereHas('devices', fn ($q) => $q->where('is_active', true))
            ->with(['devices' => fn ($q) => $q->where('is_active', true)->select('user_id', 'device_id')])
            ->get(['users.id']);

        $result = $users->map(fn ($user) => [
            'user_id' => $user->id,
            'devices' => $user->devices->map(fn ($d) => ['device_id' => $d->device_id])->values(),
        ]);

        return $this->successResponse($result);
    }
}
