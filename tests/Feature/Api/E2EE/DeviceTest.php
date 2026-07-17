<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson(route('api.e2ee.devices.register'), ['device_id' => $this->uuid()])
            ->assertUnauthorized();
    }

    public function test_registers_a_device_with_key_packages(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);
        $deviceId = $this->uuid();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.devices.register'), [
                'device_id' => $deviceId,
                'device_name' => 'My Desktop',
                'key_packages' => [
                    ['key_package_bytes' => base64_encode('kp1'), 'key_package_hash' => $this->kpHash()],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.device_id', $deviceId);

        $this->assertDatabaseHas('user_devices', ['user_id' => $user->id, 'device_id' => $deviceId, 'is_active' => true]);
        $this->assertDatabaseHas('mls_key_packages', ['device_id' => $deviceId]);
    }

    public function test_requires_an_identity_before_registering_a_device(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.devices.register'), ['device_id' => $this->uuid()])
            ->assertStatus(422);
    }

    public function test_rejects_a_duplicate_device_id(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.devices.register'), ['device_id' => $device->device_id])
            ->assertStatus(409);
    }

    public function test_enforces_the_ten_device_limit(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);
        for ($i = 0; $i < 10; $i++) {
            $this->registerDevice($user);
        }

        $this->actingAs($user)
            ->postJson(route('api.e2ee.devices.register'), ['device_id' => $this->uuid()])
            ->assertStatus(422);
    }

    public function test_validates_device_id_is_a_uuid(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.devices.register'), ['device_id' => 'not-a-uuid'])
            ->assertStatus(422);
    }

    public function test_lists_only_active_devices(): void
    {
        $user = User::factory()->create();
        $this->registerDevice($user);
        $this->registerDevice($user, null, false); // inactive

        $this->actingAs($user)
            ->getJson(route('api.e2ee.devices.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_revokes_a_device(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        $this->addKeyPackage($user, $device->device_id);

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]))
            ->assertOk();

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'is_active' => false]);
        $this->assertDatabaseMissing('mls_key_packages', ['device_id' => $device->device_id]);
    }

    public function test_revoking_an_unknown_device_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $this->uuid()]))
            ->assertNotFound();
    }

    public function test_updates_a_device_name(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->putJson(route('api.e2ee.devices.updateName', ['deviceId' => $device->device_id]), ['device_name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.device_name', 'Renamed');

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'device_name' => 'Renamed']);
    }

    public function test_cannot_revoke_another_users_device(): void
    {
        $owner = User::factory()->create();
        $device = $this->registerDevice($owner);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]))
            ->assertNotFound();

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'is_active' => true]);
    }
}
