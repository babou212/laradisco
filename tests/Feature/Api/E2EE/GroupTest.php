<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\DirectMessageGroup;
use App\Models\MlsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    /** @return array{0: User, 1: string, 2: string} */
    private function participantWithDevice(): array
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $user->id]);
        $group->participants()->attach($user->id);
        $device = $this->registerDevice($user);

        return [$user, 'dm:'.$group->id, $device->device_id];
    }

    public function test_claims_a_fresh_group(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithDevice();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertCreated()
            ->assertJsonPath('data.group_id', $groupId);

        $this->assertDatabaseHas('mls_groups', ['group_id' => $groupId, 'creator_user_id' => $user->id]);
    }

    public function test_reclaim_by_the_creator_requires_force(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithDevice();
        $this->claimGroup($user, $groupId, $deviceId, 4);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertStatus(409);
        $this->assertDatabaseHas('mls_groups', ['group_id' => $groupId, 'current_epoch' => 4]);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => $groupId]), ['device_id' => $deviceId, 'force' => true])
            ->assertOk()
            ->assertJsonPath('data.reclaimed', true);

        $this->assertDatabaseHas('mls_groups', ['group_id' => $groupId, 'current_epoch' => 0]);
        $this->assertDatabaseHas('e2ee_audit_log', ['user_id' => $user->id, 'event_type' => 'group_reclaimed']);
    }

    public function test_claiming_a_group_owned_by_another_user_conflicts(): void
    {
        $owner = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $owner->id]);
        $other = User::factory()->create();
        $group->participants()->attach([$owner->id, $other->id]);
        $groupId = 'dm:'.$group->id;
        $this->claimGroup($owner, $groupId, $this->uuid(), 0);
        $otherDevice = $this->registerDevice($other);

        $this->actingAs($other)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => $groupId]), ['device_id' => $otherDevice->device_id])
            ->assertStatus(409);
    }

    public function test_force_reclaim_by_non_creator_is_rejected_and_preserves_state(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $creator->id]);
        $group->participants()->attach([$creator->id, $other->id]);
        $groupId = 'dm:'.$group->id;
        $this->claimGroup($creator, $groupId, $this->uuid(), 3);
        MlsMessage::create([
            'group_id' => $groupId, 'sender_user_id' => $creator->id, 'sender_device_id' => $this->uuid(),
            'message_type' => 'commit', 'message_bytes' => base64_encode('c'), 'epoch' => 3,
        ]);
        $otherDevice = $this->registerDevice($other);

        // Even with force=true a non-creator cannot take over or wipe the group.
        $this->actingAs($other)
            ->postJson(route('api.e2ee.mls.groups.claim', ['groupId' => $groupId]), ['device_id' => $otherDevice->device_id, 'force' => true])
            ->assertStatus(409);

        $this->assertDatabaseHas('mls_groups', ['group_id' => $groupId, 'creator_user_id' => $creator->id, 'current_epoch' => 3]);
        $this->assertSame(1, MlsMessage::where('group_id', $groupId)->count());
    }

    public function test_status_reports_existence(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithDevice();

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.groups.status', ['groupId' => $groupId]))
            ->assertOk()
            ->assertJsonPath('data.exists', false);

        $this->claimGroup($user, $groupId, $deviceId, 0);

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.groups.status', ['groupId' => $groupId]))
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.creator_user_id', $user->id);
    }

    public function test_user_groups_lists_dm_groups(): void
    {
        [$user, $groupId] = $this->participantWithDevice();

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.userGroups'))
            ->assertOk()
            ->assertJsonFragment([$groupId]);
    }

    public function test_dm_member_bundles_returns_participant_devices(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);
        $this->registerDevice($alice);
        $bobDevice = $this->registerDevice($bob);

        $response = $this->actingAs($alice)
            ->getJson(route('api.e2ee.dmGroups.members.bundles', $group))
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $bobBundle = collect($data)->firstWhere('user_id', $bob->id);
        $this->assertSame($bobDevice->device_id, $bobBundle['devices'][0]['device_id']);
    }

    public function test_dm_member_bundles_forbidden_for_non_participant(): void
    {
        $alice = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach($alice->id);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('api.e2ee.dmGroups.members.bundles', $group))
            ->assertForbidden();
    }
}
