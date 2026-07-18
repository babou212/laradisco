<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\DirectMessageGroup;
use App\Models\MlsWelcomeMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    public function test_submits_a_welcome_to_a_participant_device(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $bobDevice = $this->registerDevice($bob);
        $groupId = 'dm:'.$group->id;

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.groups.welcome.submit', ['groupId' => $groupId]), [
                'recipient_user_id' => $bob->id,
                'recipient_device_id' => $bobDevice->device_id,
                'welcome_bytes' => base64_encode('welcome'),
                'ratchet_tree_bytes' => base64_encode('tree'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.recipients_count', 1);

        $this->assertDatabaseHas('mls_welcome_messages', [
            'group_id' => $groupId,
            'recipient_user_id' => $bob->id,
            'recipient_device_id' => $bobDevice->device_id,
        ]);
    }

    public function test_submits_a_batch_of_welcomes(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $d1 = $this->registerDevice($bob);
        $d2 = $this->registerDevice($bob);
        $groupId = 'dm:'.$group->id;

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.groups.welcome.submit', ['groupId' => $groupId]), [
                'recipients' => [
                    ['user_id' => $bob->id, 'device_id' => $d1->device_id, 'welcome_bytes' => base64_encode('w1')],
                    ['user_id' => $bob->id, 'device_id' => $d2->device_id, 'welcome_bytes' => base64_encode('w2')],
                ],
                'ratchet_tree_bytes' => base64_encode('tree'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.recipients_count', 2);
    }

    public function test_rejects_a_welcome_for_a_non_participant(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stranger = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $strangerDevice = $this->registerDevice($stranger);
        $groupId = 'dm:'.$group->id;

        $this->actingAs($alice)
            ->postJson(route('api.e2ee.mls.groups.welcome.submit', ['groupId' => $groupId]), [
                'recipient_user_id' => $stranger->id,
                'recipient_device_id' => $strangerDevice->device_id,
                'welcome_bytes' => base64_encode('w'),
                'ratchet_tree_bytes' => base64_encode('tree'),
            ])
            ->assertForbidden();
    }

    public function test_fetch_consumes_welcomes_for_a_device(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);
        MlsWelcomeMessage::create([
            'group_id' => 'dm:1',
            'recipient_user_id' => $user->id,
            'recipient_device_id' => $device->device_id,
            'welcome_bytes' => base64_encode('w'),
            'ratchet_tree_bytes' => base64_encode('t'),
        ]);

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.welcome.fetch', ['device_id' => $device->device_id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group_id', 'dm:1');

        $this->assertDatabaseMissing('mls_welcome_messages', [
            'recipient_device_id' => $device->device_id,
            'consumed_at' => null,
        ]);
    }

    public function test_fetch_requires_a_device_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.welcome.fetch'))
            ->assertStatus(422);
    }

    public function test_fetch_rejects_an_invalid_device(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.welcome.fetch', ['device_id' => $this->uuid()]))
            ->assertForbidden();
    }
}
