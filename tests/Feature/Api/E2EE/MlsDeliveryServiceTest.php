<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Smoke test for the resurrected MLS delivery-service endpoints: identity +
 * device registration, key-package pool, commit ordering (the epoch-409 that
 * prevents group forks), and ciphertext DM storage over the normal DM route.
 */
class MlsDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function hash(): string
    {
        // 64-char hex, matching the key_package_hash size:64 rule.
        return str_repeat('ab', 32);
    }

    public function test_delivery_service_happy_path_and_epoch_conflict(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $deviceId = (string) Str::uuid();

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.identity.register'), [
                'identity_key' => str_repeat('k', 44),
            ])->assertSuccessful();

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.devices.register'), [
                'device_id' => $deviceId,
                'device_name' => 'Alice Desktop',
            ])->assertSuccessful();

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.keyPackages.upload'), [
                'device_id' => $deviceId,
                'key_packages' => [
                    ['key_package_bytes' => base64_encode('kp-1'), 'key_package_hash' => $this->hash()],
                ],
            ])->assertSuccessful();

        $this->actingAs($alice)
            ->getJson(route('api.e2ee.mls.keyPackages.count').'?device_id='.$deviceId)
            ->assertSuccessful();

        $groupId = 'dm:'.$group->id;

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
            ])->assertSuccessful();

        // First commit at epoch 1 advances the group.
        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('commit-epoch-1'),
                'epoch' => 1,
            ])->assertSuccessful();

        // A second commit at the same epoch must be rejected (fork prevention).
        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('commit-epoch-1-again'),
                'epoch' => 1,
            ])->assertStatus(409);
    }

    public function test_dm_edit_stores_ciphertext_and_nulls_content(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $message = $group->messages()->create([
            'user_id' => $alice->id,
            'message_bytes' => base64_encode('original'),
            'sender_device_id' => (string) Str::uuid(),
            'epoch' => 1,
        ]);

        $this->actingAs($alice)->putJson(
            route('api.direct-messages.messages.update', [$group, $message]),
            [
                'message_bytes' => base64_encode('edited-ciphertext'),
                'sender_device_id' => (string) Str::uuid(),
                'epoch' => 1,
            ],
        )->assertOk();

        $message->refresh();
        $this->assertSame(base64_encode('edited-ciphertext'), $message->message_bytes);
        $this->assertNull($message->content);
        $this->assertTrue((bool) $message->is_edited);
    }

    public function test_non_participant_cannot_claim_dm_group(): void
    {
        $alice = User::factory()->create();
        $stranger = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id]);

        $this->actingAs($stranger)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => 'dm:'.$group->id]), [
                'device_id' => (string) Str::uuid(),
            ])->assertForbidden();
    }

    public function test_dm_store_persists_ciphertext_instead_of_plaintext(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $deviceId = (string) Str::uuid();

        $response = $this->actingAs($alice)->postJson(
            route('api.direct-messages.messages.store', $group),
            [
                'message_bytes' => base64_encode('mls-ciphertext-payload'),
                'sender_device_id' => $deviceId,
                'epoch' => 3,
                'client_temp_id' => (string) Str::uuid(),
            ],
        );

        $response->assertCreated();
        // The resource exposes ciphertext for the client to decrypt, not plaintext.
        $response->assertJsonPath('data.attributes.message_bytes', base64_encode('mls-ciphertext-payload'));
        $response->assertJsonPath('data.attributes.content', null);
        $response->assertJsonPath('data.attributes.epoch', 3);

        $message = DirectMessage::query()->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame(base64_encode('mls-ciphertext-payload'), $message->message_bytes);
        $this->assertSame($deviceId, $message->sender_device_id);
        $this->assertSame(3, (int) $message->epoch);
        $this->assertNull($message->content);
    }

    public function test_plaintext_dm_store_is_rejected(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        $this->actingAs($alice)->postJson(
            route('api.direct-messages.messages.store', $group),
            ['content' => 'plaintext is not allowed'],
        )->assertUnprocessable()->assertJsonFragment(['detail' => 'The content field is prohibited.']);

        $this->assertSame(0, DirectMessage::query()->count());
    }

    public function test_plaintext_dm_edit_is_rejected(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $message = $group->messages()->create([
            'user_id' => $alice->id,
            'message_bytes' => base64_encode('original'),
            'sender_device_id' => (string) Str::uuid(),
            'epoch' => 1,
        ]);

        $this->actingAs($alice)->putJson(
            route('api.direct-messages.messages.update', [$group, $message]),
            ['content' => 'plaintext edit'],
        )->assertUnprocessable()->assertJsonFragment(['detail' => 'The content field is prohibited.']);

        $message->refresh();
        $this->assertSame(base64_encode('original'), $message->message_bytes);
        $this->assertNull($message->content);
    }
}
