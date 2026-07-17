<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\MlsGroup;
use App\Models\MlsKeyPackage;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserIdentityKey;
use Illuminate\Support\Str;

/**
 * Shared row-builders for E2EE feature tests. No factories exist for the MLS
 * models, so tests seed prerequisites directly via the fillable columns.
 */
trait InteractsWithE2ee
{
    protected function uuid(): string
    {
        return (string) Str::uuid();
    }

    protected function registerIdentity(User $user): UserIdentityKey
    {
        return UserIdentityKey::create([
            'user_id' => $user->id,
            'identity_key' => str_repeat('k', 44),
        ]);
    }

    protected function registerDevice(User $user, ?string $deviceId = null, bool $active = true): UserDevice
    {
        return UserDevice::create([
            'user_id' => $user->id,
            'device_id' => $deviceId ?? $this->uuid(),
            'device_name' => 'Test Device',
            'is_active' => $active,
        ]);
    }

    protected function addKeyPackage(User $user, string $deviceId, ?string $hash = null): MlsKeyPackage
    {
        return MlsKeyPackage::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'key_package_bytes' => base64_encode('kp-'.Str::random(8)),
            'key_package_hash' => $hash ?? bin2hex(random_bytes(32)),
        ]);
    }

    protected function claimGroup(User $user, string $groupId, string $deviceId, int $epoch = 0): MlsGroup
    {
        return MlsGroup::create([
            'group_id' => $groupId,
            'creator_user_id' => $user->id,
            'creator_device_id' => $deviceId,
            'current_epoch' => $epoch,
        ]);
    }

    /** A 64-char hex hash (matches the size:64 rule). */
    protected function kpHash(): string
    {
        return bin2hex(random_bytes(32));
    }
}
