<?php

namespace Tests\Feature\Api\E2EE;

use App\Models\DirectMessageGroup;
use App\Models\MlsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MlsMessageTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    /** @return array{0: User, 1: DirectMessageGroup, 2: string, 3: string} */
    private function dmWithDevice(): array
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $user->id]);
        $group->participants()->attach($user->id);
        $device = $this->registerDevice($user);
        $groupId = 'dm:'.$group->id;

        return [$user, $group, $device->device_id, $groupId];
    }

    public function test_submits_a_commit_advancing_the_epoch(): void
    {
        [$user, , $deviceId, $groupId] = $this->dmWithDevice();
        $this->claimGroup($user, $groupId, $deviceId, 0);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('c1'),
                'epoch' => 1,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('mls_groups', ['group_id' => $groupId, 'current_epoch' => 1]);
    }

    public function test_rejects_a_stale_epoch_commit(): void
    {
        [$user, , $deviceId, $groupId] = $this->dmWithDevice();
        $this->claimGroup($user, $groupId, $deviceId, 5);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('stale'),
                'epoch' => 3,
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'mls_epoch_conflict');
    }

    public function test_commit_to_a_missing_group_row_returns_404(): void
    {
        [$user, , $deviceId, $groupId] = $this->dmWithDevice();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('c'),
                'epoch' => 1,
            ])
            ->assertNotFound();
    }

    public function test_submit_requires_a_device_id(): void
    {
        [$user, , , $groupId] = $this->dmWithDevice();
        $this->claimGroup($user, $groupId, $this->uuid(), 0);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'message_type' => 'commit',
                'message_bytes' => base64_encode('c'),
                'epoch' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_non_participant_cannot_submit(): void
    {
        [, , , $groupId] = $this->dmWithDevice();
        $stranger = User::factory()->create();
        $device = $this->registerDevice($stranger);

        $this->actingAs($stranger)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $device->device_id,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('c'),
                'epoch' => 1,
            ])
            ->assertForbidden();
    }

    public function test_invalid_group_id_format_is_rejected(): void
    {
        $user = User::factory()->create();
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => 'garbage']), [
                'device_id' => $device->device_id,
                'message_type' => 'commit',
                'message_bytes' => base64_encode('c'),
                'epoch' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_application_messages_are_accepted_and_fetchable(): void
    {
        [$user, , $deviceId, $groupId] = $this->dmWithDevice();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'application',
                'message_bytes' => base64_encode('history-sync-payload'),
                'epoch' => 2,
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.groups.messages.fetch', ['groupId' => $groupId, 'message_type' => 'application']))
            ->assertOk()
            ->assertJsonPath('data.0.message_type', 'application');
    }

    public function test_proposals_pass_through_without_epoch_checks(): void
    {
        [$user, , $deviceId, $groupId] = $this->dmWithDevice();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.messages.submit', ['groupId' => $groupId]), [
                'device_id' => $deviceId,
                'message_type' => 'proposal',
                'message_bytes' => base64_encode('p'),
                'epoch' => 7,
            ])
            ->assertCreated();
    }

    public function test_fetch_returns_messages_filtered_by_since_id_and_type(): void
    {
        [$user, , $deviceId, $groupId] = $this->dmWithDevice();
        $m1 = MlsMessage::create(['group_id' => $groupId, 'sender_user_id' => $user->id, 'sender_device_id' => $deviceId, 'message_type' => 'commit', 'message_bytes' => base64_encode('c1'), 'epoch' => 1]);
        MlsMessage::create(['group_id' => $groupId, 'sender_user_id' => $user->id, 'sender_device_id' => $deviceId, 'message_type' => 'proposal', 'message_bytes' => base64_encode('p1'), 'epoch' => 1]);

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.groups.messages.fetch', ['groupId' => $groupId, 'since_id' => $m1->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message_type', 'proposal');

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.groups.messages.fetch', ['groupId' => $groupId, 'message_type' => 'commit']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message_type', 'commit');
    }

    public function test_non_participant_cannot_fetch_messages(): void
    {
        [, , , $groupId] = $this->dmWithDevice();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('api.e2ee.mls.groups.messages.fetch', ['groupId' => $groupId]))
            ->assertForbidden();
    }
}
