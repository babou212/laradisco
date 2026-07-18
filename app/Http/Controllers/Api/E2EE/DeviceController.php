<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Events\DeviceAdded;
use App\Events\DeviceRevoked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\RegisterDeviceRequest;
use App\Http\Requests\Api\E2EE\UpdateDeviceNameRequest;
use App\Models\MlsJoinRequest;
use App\Models\MlsKeyPackage;
use App\Models\MlsWelcomeMessage;
use App\Models\UserDevice;
use App\Models\UserIdentityKey;
use App\Services\E2eeAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
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

        $existingDevice = UserDevice::where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->first();

        if ($existingDevice && $existingDevice->is_active) {
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
            UserDevice::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $validated['device_id'],
                ],
                [
                    'device_name' => $validated['device_name'] ?? null,
                    'platform' => $validated['platform'] ?? null,
                    'device_identity_key' => $validated['device_identity_key'] ?? null,
                    'identity_signature' => $validated['identity_signature'] ?? null,
                    'is_active' => true,
                ],
            );

            if (! empty($validated['key_packages'])) {
                /** @var array<int, array{key_package_hash: string, key_package_bytes: string}> $keyPackages */
                $keyPackages = $validated['key_packages'];
                $uniquePackages = collect($keyPackages)
                    ->unique('key_package_hash')
                    ->all();

                foreach ($uniquePackages as $kp) {
                    MlsKeyPackage::firstOrCreate(
                        ['key_package_hash' => $kp['key_package_hash']],
                        [
                            'user_id' => $user->id,
                            'device_id' => $validated['device_id'],
                            'key_package_bytes' => $kp['key_package_bytes'],
                            'identity_signature' => $kp['identity_signature'] ?? null,
                        ],
                    );
                }
            }
        });

        $this->auditService->logEvent(
            userId: $user->id,
            eventType: 'device_registered',
            deviceId: $validated['device_id'],
            metadata: ['device_name' => $validated['device_name'] ?? null],
        );

        try {
            broadcast(new DeviceAdded(
                userId: $user->id,
                deviceId: $validated['device_id'],
                deviceName: $validated['device_name'] ?? null,
            ));
        } catch (\Throwable $e) {
            report($e);
        }

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
        $currentDeviceId = $this->currentTokenDeviceId($request);

        $devices = UserDevice::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->select(['device_id', 'device_name', 'platform', 'last_seen_at', 'created_at'])
            ->get()
            ->map(fn (UserDevice $device) => array_merge($device->toArray(), [
                'is_current' => $device->device_id === $currentDeviceId,
            ]));

        return $this->successResponse($devices);
    }

    /**
     * Bind the current access token to a device and mark the device seen.
     * Called once per app launch so revocation can kill the right session.
     */
    public function bind(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'uuid'],
        ]);

        $device = UserDevice::where('user_id', $request->user()->id)
            ->where('device_id', $validated['device_id'])
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->forceFill(['device_id' => $device->device_id])->save();
        }

        $device->update(['last_seen_at' => now()]);

        return $this->successResponse(null, 'Device bound.');
    }

    /**
     * Revoke (deactivate) a device.
     */
    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        $user = $request->user();

        if ($deviceId !== $this->currentTokenDeviceId($request)) {
            $request->validate([
                'password' => ['required', 'string', 'current_password'],
            ]);
        }

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->notFoundResponse('Device not found.');
        }

        DB::transaction(function () use ($user, $device, $deviceId) {
            $device->update(['is_active' => false]);

            MlsKeyPackage::where('device_id', $deviceId)
                ->where('user_id', $user->id)
                ->delete();

            MlsWelcomeMessage::where('recipient_user_id', $user->id)
                ->where('recipient_device_id', $deviceId)
                ->delete();

            MlsJoinRequest::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->delete();

            $user->tokens()->where('device_id', $deviceId)->delete();
        });

        $this->auditService->logEvent(
            userId: $user->id,
            eventType: 'device_revoked',
            deviceId: $deviceId,
            metadata: ['device_name' => $device->device_name],
        );

        try {
            broadcast(new DeviceRevoked(
                userId: $user->id,
                deviceId: $deviceId,
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->successResponse(null, 'Device revoked successfully.');
    }

    private function currentTokenDeviceId(Request $request): ?string
    {
        $token = $request->user()->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->device_id : null;
    }

    /**
     * Update a device's name.
     */
    public function updateName(UpdateDeviceNameRequest $request, string $deviceId): JsonResponse
    {

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
