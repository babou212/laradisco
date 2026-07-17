<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\MlsKeyPackage;
use App\Models\MlsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson(route('api.e2ee.identity.register'), ['identity_key' => str_repeat('k', 44)])
            ->assertUnauthorized();
    }

    public function test_registers_an_identity_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.identity.register'), ['identity_key' => str_repeat('k', 44)])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('user_identity_keys', ['user_id' => $user->id]);
    }

    public function test_rejects_a_duplicate_identity(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.identity.register'), ['identity_key' => str_repeat('k', 44)])
            ->assertStatus(409);
    }

    public function test_validates_the_identity_key_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.identity.register'), ['identity_key' => 'too-short'])
            ->assertStatus(422);
    }

    public function test_shows_another_users_public_identity(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $this->registerIdentity($owner);

        $this->actingAs($viewer)
            ->getJson(route('api.e2ee.identity.show', $owner))
            ->assertOk()
            ->assertJsonPath('data.user_id', $owner->id);
    }

    public function test_show_returns_404_when_no_identity(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();

        $this->actingAs($viewer)
            ->getJson(route('api.e2ee.identity.show', $owner))
            ->assertNotFound();
    }

    public function test_reset_wipes_identity_devices_and_key_packages(): void
    {
        $user = User::factory()->create();
        $this->registerIdentity($user);
        $device = $this->registerDevice($user);
        $this->addKeyPackage($user, $device->device_id);

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.identity.reset'))
            ->assertOk();

        $this->assertDatabaseMissing('user_identity_keys', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_devices', ['user_id' => $user->id]);
        $this->assertSame(0, MlsKeyPackage::where('user_id', $user->id)->count());
    }

    public function test_reset_deletes_only_callers_own_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->registerIdentity($alice);
        $mk = fn (User $u, int $e) => MlsMessage::create([
            'group_id' => 'dm:1', 'sender_user_id' => $u->id, 'sender_device_id' => $this->uuid(),
            'message_type' => 'commit', 'message_bytes' => base64_encode('c'), 'epoch' => $e,
        ]);
        $mk($alice, 1);
        $mk($bob, 2);

        $this->actingAs($alice)->deleteJson(route('api.e2ee.identity.reset'))->assertOk();

        // Alice's own message is gone; Bob's shared-group message is untouched.
        $this->assertSame(0, MlsMessage::where('sender_user_id', $alice->id)->count());
        $this->assertSame(1, MlsMessage::where('sender_user_id', $bob->id)->count());
    }

    public function test_reset_without_identity_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('api.e2ee.identity.reset'))
            ->assertNotFound();
    }
}
