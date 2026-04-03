<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\UploadKeyPackagesRequest;
use App\Models\MlsKeyPackage;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MlsKeyPackageController extends Controller
{
    use ApiResponse;

    /**
     * Upload key packages for the authenticated user's device.
     */
    public function upload(UploadKeyPackagesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

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

        $rows = array_map(fn ($kp) => [
            'user_id' => $user->id,
            'device_id' => $device->device_id,
            'key_package_bytes' => $kp['key_package_bytes'],
            'key_package_hash' => $kp['key_package_hash'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $validated['key_packages']);

        $created = MlsKeyPackage::insertOrIgnore($rows);

        // Invalidate key package count cache for this device
        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::e2eeKeyPackageCount($device->device_id));

        return $this->createdResponse([
            'uploaded' => $created,
        ], 'Key packages uploaded.');
    }

    /**
     * Fetch one available key package per device for a given user.
     * Marks consumed key packages so they aren't reused.
     */
    public function fetch(Request $request, User $user): JsonResponse
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

                    // Invalidate key package count cache for consumed device
                    Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::e2eeKeyPackageCount($device->device_id));
                }
            }

            return $this->successResponse($packages);
        });
    }

    /**
     * Check remaining available key package count for the authenticated user's device.
     */
    public function count(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceId = $request->query('device_id');

        if ($deviceId) {
            $cacheKey = CacheKeys::e2eeKeyPackageCount($deviceId);
            $count = Cache::tags([CacheKeys::userTag($user->id)])
                ->remember($cacheKey, CacheKeys::TTL_WARM, function () use ($user, $deviceId) {
                    return MlsKeyPackage::where('user_id', $user->id)
                        ->where('device_id', $deviceId)
                        ->whereNull('consumed_at')
                        ->count();
                });

            return $this->successResponse(['count' => $count]);
        }

        $query = MlsKeyPackage::where('user_id', $user->id)
            ->whereNull('consumed_at');

        return $this->successResponse([
            'count' => $query->count(),
        ]);
    }
}
