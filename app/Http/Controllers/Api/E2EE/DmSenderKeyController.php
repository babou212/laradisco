<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Events\DmSenderKeyDistributed;
use App\Events\DmSenderKeyNeeded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\DistributeSenderKeysRequest;
use App\Models\DirectMessageGroup;
use App\Models\DmSenderKey;
use App\Models\DmSenderKeyDistribution;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DmSenderKeyController extends Controller
{
    use ApiResponse;

    /**
     * Distribute sender key to DM group participants.
     * Stores per-device encrypted distributions — the server never sees the raw key material.
     */
    public function distribute(DistributeSenderKeysRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse('You are not a participant in this DM group.');
        }

        $validated = $request->validated();

        $senderDevice = UserDevice::where('device_id', $validated['device_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $senderDevice) {
            return $this->forbiddenResponse('Invalid sender device for this user.');
        }

        DmSenderKey::upsert(
            [
                'dm_group_id' => $dmGroup->id,
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
                'distribution_id' => $validated['distribution_id'],
            ],
            ['dm_group_id', 'user_id', 'device_id'],
            ['distribution_id'],
        );

        if (! empty($validated['distributions'])) {
            foreach ($validated['distributions'] as $dist) {
                if (! $dmGroup->participants()->where('users.id', $dist['recipient_user_id'])->exists()) {
                    return $this->forbiddenResponse('Recipient is not a participant in this DM group.');
                }

                $recipientDevice = UserDevice::where('device_id', $dist['recipient_device_id'])
                    ->where('user_id', $dist['recipient_user_id'])
                    ->first();

                if (! $recipientDevice) {
                    return $this->forbiddenResponse('Invalid recipient device for this user.');
                }

                DmSenderKeyDistribution::upsert(
                    [
                        'dm_group_id' => $dmGroup->id,
                        'sender_device_id' => $validated['device_id'],
                        'distribution_id' => $validated['distribution_id'],
                        'recipient_device_id' => $dist['recipient_device_id'],
                        'sender_user_id' => $user->id,
                        'recipient_user_id' => $dist['recipient_user_id'],
                        'encrypted_distribution' => $dist['encrypted_distribution'],
                        'ephemeral_public_key' => $dist['ephemeral_public_key'],
                        'nonce' => $dist['nonce'],
                    ],
                    ['dm_group_id', 'sender_device_id', 'distribution_id', 'recipient_device_id'],
                    ['sender_user_id', 'recipient_user_id', 'encrypted_distribution', 'ephemeral_public_key', 'nonce'],
                );
            }

            try {
                broadcast(new DmSenderKeyDistributed(
                    dmGroupId: $dmGroup->id,
                    senderUserId: $user->id,
                    senderDeviceId: $validated['device_id'],
                ))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->createdResponse([
            'dm_group_id' => $dmGroup->id,
            'device_id' => $validated['device_id'],
            'distribution_id' => $validated['distribution_id'],
        ], 'Sender key distributed.');
    }

    /**
     * Get sender key distributions for a DM group, filtered to the requesting device.
     */
    public function index(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse('You are not a participant in this DM group.');
        }

        $deviceId = $request->query('device_id');

        $query = DmSenderKeyDistribution::where('dm_group_id', $dmGroup->id)
            ->where('recipient_user_id', $user->id);

        if ($deviceId) {
            $query->where('recipient_device_id', $deviceId);
        }

        $distributions = $query->select([
            'sender_user_id',
            'sender_device_id',
            'distribution_id',
            'recipient_device_id',
            'encrypted_distribution',
            'ephemeral_public_key',
            'nonce',
            'updated_at',
        ])->get();

        return $this->successResponse($distributions);
    }

    /**
     * Invalidate all sender keys for a DM group.
     * Called when membership changes to force re-keying.
     */
    public function invalidate(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse('You are not a participant in this DM group.');
        }

        $deleted = DmSenderKey::where('dm_group_id', $dmGroup->id)->delete();
        DmSenderKeyDistribution::where('dm_group_id', $dmGroup->id)->delete();

        return $this->successResponse([
            'dm_group_id' => $dmGroup->id,
            'keys_invalidated' => $deleted,
        ], 'All sender keys invalidated. Participants must redistribute new keys.');
    }

    /**
     * Get active device identity keys for all DM group participants.
     * Used by the sender to encrypt sender key distributions per-device.
     */
    public function memberBundles(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse('You are not a participant in this DM group.');
        }

        $memberBundles = $dmGroup->participants()
            ->whereHas('activeDevices')
            ->with('activeDevices:id,user_id,device_id,device_identity_key')
            ->get()
            ->map(fn (User $u) => [
                'user_id' => $u->id,
                'devices' => $u->activeDevices->map(fn (UserDevice $d) => [
                    'device_id' => $d->device_id,
                    'device_identity_key' => $d->device_identity_key,
                ])->values(),
            ])
            ->values();

        return $this->successResponse($memberBundles);
    }

    /**
     * Request sender key distributions from online DM group participants.
     */
    public function requestKeys(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse('You are not a participant in this DM group.');
        }

        $deviceId = $request->input('device_id');
        if (! $deviceId) {
            return $this->errorResponse('device_id is required.', 422);
        }

        try {
            broadcast(new DmSenderKeyNeeded(
                dmGroupId: $dmGroup->id,
                requestingUserId: $user->id,
                requestingDeviceId: $deviceId,
            ))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->successResponse([
            'dm_group_id' => $dmGroup->id,
            'device_id' => $deviceId,
        ], 'Sender key request broadcast to DM group participants.');
    }
}
