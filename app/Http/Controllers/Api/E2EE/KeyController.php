<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\ReplenishPrekeysRequest;
use App\Http\Requests\Api\E2EE\RotateSignedPrekeyRequest;
use App\Models\DevicePrekey;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserIdentityKey;
use App\Services\E2eeAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly E2eeAuditService $auditService,
    ) {}

    /**
     * List the active device IDs and identity keys for a user WITHOUT consuming
     * any one-time pre-keys. Used by senders to check whether there are new
     * devices that need X3DH sessions, before fetching the full bundle (which
     * does consume OTP keys).
     */
    public function deviceList(Request $request, User $user): JsonResponse
    {
        $identityKey = UserIdentityKey::where('user_id', $user->id)->first();

        if (! $identityKey) {
            return $this->notFoundResponse('User has not set up E2EE.');
        }

        $devices = UserDevice::where('user_id', $user->id)
            ->where('is_active', true)
            ->select(['device_id', 'device_name', 'device_identity_key'])
            ->get()
            ->map(fn (UserDevice $device) => [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'device_identity_key' => $device->device_identity_key,
            ]);

        return $this->successResponse($devices);
    }

    /**
     * Get the full key bundle for ALL active devices of a user.
     * Used by senders to establish sessions with each device.
     * NOTE: This endpoint consumes one-time pre-keys. Use deviceList()
     * first to check whether a full bundle fetch is necessary.
     */
    public function bundle(Request $request, User $user): JsonResponse
    {
        $identityKey = UserIdentityKey::where('user_id', $user->id)->first();

        if (! $identityKey) {
            return $this->notFoundResponse('User has not set up E2EE.');
        }

        $devices = UserDevice::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        /** @var list<array<string, mixed>> $bundles */
        $bundles = DB::transaction(function () use ($devices) {
            return $devices->map(function (UserDevice $device) {
                $oneTimePrekey = DevicePrekey::where('device_id', $device->device_id)
                    ->where('user_id', $device->user_id)
                    ->where('used', false)
                    ->orderBy('prekey_id')
                    ->lockForUpdate()
                    ->first();

                if ($oneTimePrekey) {
                    $oneTimePrekey->update(['used' => true]);
                }

                return [
                    'device_id' => $device->device_id,
                    'device_name' => $device->device_name,
                    'device_identity_key' => $device->device_identity_key,
                    'identity_signature' => $device->identity_signature,
                    'signed_prekey' => $device->signed_prekey,
                    'signed_prekey_id' => $device->signed_prekey_id,
                    'signed_prekey_signature' => $device->signed_prekey_signature,
                    'one_time_prekey' => $oneTimePrekey ? [
                        'prekey_id' => $oneTimePrekey->prekey_id,
                        'public_key' => $oneTimePrekey->public_key,
                    ] : null,
                ];
            })->all();
        });

        return $this->successResponse([
            'user_id' => $user->id,
            'identity_key' => $identityKey->identity_key,
            'devices' => $bundles,
        ]);
    }

    /**
     * Upload new one-time pre-keys for a device.
     */
    public function replenishPrekeys(ReplenishPrekeysRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        $created = 0;
        foreach ($validated['prekeys'] as $prekey) {
            $existing = DevicePrekey::where('device_id', $validated['device_id'])
                ->where('prekey_id', $prekey['prekey_id'])
                ->first();

            if (! $existing) {
                DevicePrekey::create([
                    'device_id' => $validated['device_id'],
                    'prekey_id' => $prekey['prekey_id'],
                    'user_id' => $user->id,
                    'public_key' => $prekey['public_key'],
                    'used' => false,
                    'created_at' => now(),
                ]);
                $created++;
            }
        }

        return $this->successResponse([
            'device_id' => $validated['device_id'],
            'prekeys_added' => $created,
        ], "{$created} pre-keys added.");
    }

    /**
     * Rotate the signed pre-key for a device.
     */
    public function rotateSignedPrekey(RotateSignedPrekeyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        $device->update([
            'signed_prekey' => $validated['signed_prekey'],
            'signed_prekey_id' => $validated['signed_prekey_id'],
            'signed_prekey_signature' => $validated['signed_prekey_signature'],
            'signed_prekey_timestamp' => now(),
        ]);

        $this->auditService->logEvent(
            userId: $user->id,
            eventType: 'signed_prekey_rotated',
            deviceId: $validated['device_id'],
            publicKey: $validated['signed_prekey'],
            signature: $validated['signed_prekey_signature'],
        );

        return $this->successResponse([
            'device_id' => $validated['device_id'],
            'signed_prekey_id' => $validated['signed_prekey_id'],
        ], 'Signed pre-key rotated.');
    }

    /**
     * Check remaining unused one-time pre-keys for this device.
     */
    public function prekeyCount(Request $request): JsonResponse
    {
        $request->validate(['device_id' => ['required', 'string', 'uuid']]);

        $device = UserDevice::where('user_id', $request->user()->id)
            ->where('device_id', $request->input('device_id'))
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        $count = DevicePrekey::where('device_id', $request->input('device_id'))
            ->where('user_id', $request->user()->id)
            ->where('used', false)
            ->count();

        return $this->successResponse([
            'device_id' => $request->input('device_id'),
            'remaining_prekeys' => $count,
        ]);
    }
}
