<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Events\SenderKeyDistributed;
use App\Events\SenderKeyNeeded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\DistributeSenderKeysRequest;
use App\Models\Channel;
use App\Models\ChannelSenderKey;
use App\Models\SenderKeyDistribution;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SenderKeyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Distribute sender key to channel members.
     * Stores per-device encrypted distributions — the server never sees the raw key material.
     */
    public function distribute(DistributeSenderKeysRequest $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $validated = $request->validated();

        ChannelSenderKey::updateOrCreate(
            [
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
            ],
            [
                'distribution_id' => $validated['distribution_id'],
            ],
        );

        if (! empty($validated['distributions'])) {
            foreach ($validated['distributions'] as $dist) {
                SenderKeyDistribution::updateOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'sender_device_id' => $validated['device_id'],
                        'distribution_id' => $validated['distribution_id'],
                        'recipient_device_id' => $dist['recipient_device_id'],
                    ],
                    [
                        'sender_user_id' => $user->id,
                        'recipient_user_id' => $dist['recipient_user_id'],
                        'encrypted_distribution' => $dist['encrypted_distribution'],
                        'ephemeral_public_key' => $dist['ephemeral_public_key'],
                        'nonce' => $dist['nonce'],
                    ],
                );
            }

            broadcast(new SenderKeyDistributed(
                channelId: $channel->id,
                senderUserId: $user->id,
                senderDeviceId: $validated['device_id'],
            ))->toOthers();
        }

        return $this->createdResponse([
            'channel_id' => $channel->id,
            'device_id' => $validated['device_id'],
            'distribution_id' => $validated['distribution_id'],
        ], 'Sender key distributed.');
    }

    /**
     * Get sender key distributions for a channel, filtered to the requesting device.
     * Returns only encrypted blobs addressed to the user's devices.
     */
    public function index(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $deviceId = $request->query('device_id');

        $query = SenderKeyDistribution::where('channel_id', $channel->id)
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
     * Invalidate all sender keys for a channel.
     * Called when membership changes (member leave, device revocation) to force re-keying.
     * Only channel members can trigger this.
     */
    public function invalidate(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $deleted = ChannelSenderKey::where('channel_id', $channel->id)->delete();
        SenderKeyDistribution::where('channel_id', $channel->id)->delete();

        return $this->successResponse([
            'channel_id' => $channel->id,
            'keys_invalidated' => $deleted,
        ], 'All sender keys invalidated. Members must redistribute new keys.');
    }

    /**
     * Get active device identity keys for all members who can view a channel.
     * Used by the sender to encrypt sender key distributions per-device.
     */
    public function memberBundles(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $usersWithDevices = User::whereHas('activeDevices')
            ->with('activeDevices:id,user_id,device_id,device_identity_key')
            ->get();

        $memberBundles = $usersWithDevices
            ->filter(fn (User $u) => $this->permissionService->userCanViewChannel($u, $channel))
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
     * Request sender key distributions from online channel members.
     * Broadcasts a SenderKeyNeeded event on the channel so that other
     * online clients redistribute their sender keys to the requesting device.
     */
    public function requestKeys(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        $deviceId = $request->input('device_id');
        if (! $deviceId) {
            return $this->errorResponse('device_id is required.', 422);
        }

        broadcast(new SenderKeyNeeded(
            channelId: $channel->id,
            requestingUserId: $user->id,
            requestingDeviceId: $deviceId,
        ))->toOthers();

        return $this->successResponse([
            'channel_id' => $channel->id,
            'device_id' => $deviceId,
        ], 'Sender key request broadcast to channel members.');
    }
}
