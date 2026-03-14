<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\RegisterDeviceRequest;
use App\Models\DevicePrekey;
use App\Models\DmSenderKey;
use App\Models\DmSenderKeyDistribution;
use App\Models\SenderKeyDistribution;
use App\Models\UserDevice;
use App\Models\UserIdentityKey;
use App\Services\E2eeAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DeviceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly E2eeAuditService $auditService,
    ) {}

    /**
     * Register a new device with its key material.
     */
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $identityKey = UserIdentityKey::where('user_id', $user->id)->first();

        if (! $identityKey) {
            return $this->errorResponse(
                'You must register an identity key before registering a device.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $deviceIdentityKeyBytes = base64_decode($validated['device_identity_key'], true);
        $signatureBytes = base64_decode($validated['identity_signature'], true);
        $identityKeyBytes = base64_decode($identityKey->identity_key, true);

        if ($deviceIdentityKeyBytes === false || $signatureBytes === false || $identityKeyBytes === false) {
            return $this->errorResponse(
                'Invalid base64 encoding in key material.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (! sodium_crypto_sign_verify_detached($signatureBytes, $deviceIdentityKeyBytes, $identityKeyBytes)) {
            return $this->errorResponse(
                'Device identity key signature does not match your registered identity key. '
                .'You may need to re-setup E2EE.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $existingDevice = UserDevice::where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->first();

        if ($existingDevice) {
            return $this->errorResponse(
                'Device already registered.',
                Response::HTTP_CONFLICT,
            );
        }

        $activeDeviceCount = UserDevice::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        if ($activeDeviceCount >= 10) {
            return $this->errorResponse(
                'Maximum device limit reached (10). Please revoke an existing device first.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        DB::transaction(function () use ($user, $validated) {
            $device = UserDevice::create([
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
                'device_name' => $validated['device_name'] ?? null,
                'device_identity_key' => $validated['device_identity_key'],
                'identity_signature' => $validated['identity_signature'],
                'signed_prekey' => $validated['signed_prekey'],
                'signed_prekey_id' => $validated['signed_prekey_id'],
                'signed_prekey_signature' => $validated['signed_prekey_signature'],
                'signed_prekey_timestamp' => now(),
                'is_active' => true,
            ]);

            foreach ($validated['one_time_prekeys'] as $prekey) {
                DevicePrekey::create([
                    'device_id' => $validated['device_id'],
                    'user_id' => $user->id,
                    'prekey_id' => $prekey['prekey_id'],
                    'public_key' => $prekey['public_key'],
                    'created_at' => now(),
                ]);
            }
        });

        $this->auditService->logEvent(
            userId: $user->id,
            eventType: 'device_registered',
            deviceId: $validated['device_id'],
            publicKey: $validated['device_identity_key'],
            signature: $validated['identity_signature'],
            metadata: ['device_name' => $validated['device_name'] ?? null],
        );

        return $this->createdResponse([
            'device_id' => $validated['device_id'],
            'device_name' => $validated['device_name'] ?? null,
        ], 'Device registered successfully.');
    }

    /**
     * List all active devices for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $devices = UserDevice::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->select(['device_id', 'device_name', 'last_seen_at', 'created_at'])
            ->get();

        return $this->successResponse($devices);
    }

    /**
     * Revoke (deactivate) a device.
     */
    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        $device = UserDevice::where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        $device->update(['is_active' => false]);

        DevicePrekey::where('device_id', $deviceId)
            ->where('user_id', $request->user()->id)
            ->update(['used' => true]);

        \App\Models\ChannelSenderKey::where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->delete();

        DmSenderKey::where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->delete();

        DmSenderKeyDistribution::where('sender_device_id', $deviceId)
            ->where('sender_user_id', $request->user()->id)
            ->delete();

        DmSenderKeyDistribution::where('recipient_device_id', $deviceId)
            ->where('recipient_user_id', $request->user()->id)
            ->delete();

        SenderKeyDistribution::where('sender_device_id', $deviceId)
            ->where('sender_user_id', $request->user()->id)
            ->delete();

        SenderKeyDistribution::where('recipient_device_id', $deviceId)
            ->where('recipient_user_id', $request->user()->id)
            ->delete();

        $this->auditService->logEvent(
            userId: $request->user()->id,
            eventType: 'device_revoked',
            deviceId: $deviceId,
            metadata: ['device_name' => $device->device_name],
        );

        return $this->successResponse(null, 'Device revoked successfully.');
    }

    /**
     * Update a device's name.
     */
    public function updateName(Request $request, string $deviceId): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string', 'max:255']]);

        $device = UserDevice::where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        $device->update(['device_name' => $request->input('device_name')]);

        return $this->successResponse([
            'device_id' => $deviceId,
            'device_name' => $request->input('device_name'),
        ], 'Device name updated.');
    }
}
