<?php

namespace Tests\Feature\Api\E2EE;

use App\Events\DeviceRevoked;
use App\Models\MlsJoinRequest;
use App\Models\MlsWelcomeMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]), ['password' => 'password'])
            ->assertOk();

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'is_active' => false]);
        $this->assertDatabaseMissing('mls_key_packages', ['device_id' => $device->device_id]);
    }

    public function test_revoking_another_device_requires_the_password(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]))
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]), ['password' => 'wrong'])
            ->assertStatus(422);

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'is_active' => true]);
    }

    public function test_self_revoke_needs_no_password_when_token_is_bound(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        $token = $user->createToken('desktop');
        $token->accessToken->forceFill(['device_id' => $device->device_id])->save();

        $this->withToken($token->plainTextToken)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]))
            ->assertOk();

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'is_active' => false]);
    }

    public function test_revoke_deletes_bound_tokens_purges_mls_rows_and_broadcasts(): void
    {
        Event::fake([DeviceRevoked::class]);

        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        $otherDevice = $this->registerDevice($user);

        $revokedToken = $user->createToken('revoked-session');
        $revokedToken->accessToken->forceFill(['device_id' => $device->device_id])->save();
        $survivingToken = $user->createToken('other-session');
        $survivingToken->accessToken->forceFill(['device_id' => $otherDevice->device_id])->save();

        MlsWelcomeMessage::create([
            'group_id' => $this->uuid(),
            'recipient_user_id' => $user->id,
            'recipient_device_id' => $device->device_id,
            'welcome_bytes' => base64_encode('welcome'),
            'ratchet_tree_bytes' => base64_encode('tree'),
        ]);
        MlsJoinRequest::create([
            'group_id' => $this->uuid(),
            'user_id' => $user->id,
            'device_id' => $device->device_id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]), ['password' => 'password'])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['device_id' => $device->device_id]);
        $this->assertDatabaseHas('personal_access_tokens', ['device_id' => $otherDevice->device_id]);
        $this->assertDatabaseMissing('mls_welcome_messages', ['recipient_device_id' => $device->device_id]);
        $this->assertDatabaseMissing('mls_join_requests', ['device_id' => $device->device_id]);

        Event::assertDispatched(DeviceRevoked::class, fn (DeviceRevoked $event) => $event->userId === $user->id
            && $event->deviceId === $device->device_id);
    }

    public function test_bind_stamps_the_token_and_last_seen(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        $token = $user->createToken('desktop');

        $this->withToken($token->plainTextToken)
            ->postJson(route('api.e2ee.devices.bind'), ['device_id' => $device->device_id])
            ->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
            'device_id' => $device->device_id,
        ]);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_bind_rejects_a_device_the_user_does_not_own(): void
    {
        $owner = User::factory()->create();
        $device = $this->registerDevice($owner);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)
            ->postJson(route('api.e2ee.devices.bind'), ['device_id' => $device->device_id])
            ->assertNotFound();
    }

    public function test_index_returns_platform_and_current_flag(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        $device->update(['platform' => 'linux']);
        $token = $user->createToken('desktop');
        $token->accessToken->forceFill(['device_id' => $device->device_id])->save();

        $this->withToken($token->plainTextToken)
            ->getJson(route('api.e2ee.devices.index'))
            ->assertOk()
            ->assertJsonPath('data.0.platform', 'linux')
            ->assertJsonPath('data.0.is_current', true);
    }

    public function test_reregistering_a_revoked_device_reactivates_it(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);
        $device = $this->registerDevice($user, null, false); // revoked

        $this->actingAs($user)
            ->postJson(route('api.e2ee.devices.register'), [
                'device_id' => $device->device_id,
                'device_name' => 'Restored Desktop',
                'platform' => 'linux',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('user_devices', [
            'device_id' => $device->device_id,
            'is_active' => true,
            'device_name' => 'Restored Desktop',
            'platform' => 'linux',
        ]);
    }

    public function test_revoking_an_unknown_device_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $this->uuid()]), ['password' => 'password'])
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
            ->deleteJson(route('api.e2ee.devices.destroy', ['deviceId' => $device->device_id]), ['password' => 'password'])
            ->assertNotFound();

        $this->assertDatabaseHas('user_devices', ['device_id' => $device->device_id, 'is_active' => true]);
    }
}
