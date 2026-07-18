<?php

namespace Tests\Feature\Api\E2EE;

use App\Events\MlsJoinRequested;
use App\Models\DirectMessageGroup;
use App\Models\MlsJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class JoinRequestTest extends TestCase
{
    use InteractsWithE2ee;
    use RefreshDatabase;

    /** @return array{0: User, 1: string, 2: string} */
    private function participantWithClaimedGroup(): array
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $user->id]);
        $group->participants()->attach($user->id);
        $device = $this->registerDevice($user);
        $groupId = 'dm:'.$group->id;
        $this->claimGroup($user, $groupId, $this->uuid(), 0);

        return [$user, $groupId, $device->device_id];
    }

    public function test_submits_a_join_request(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithClaimedGroup();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.joinRequest', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('mls_join_requests', ['group_id' => $groupId, 'device_id' => $deviceId, 'status' => 'pending']);
    }

    public function test_join_request_to_a_missing_group_row_returns_404(): void
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $user->id]);
        $group->participants()->attach($user->id);
        $device = $this->registerDevice($user);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.joinRequest', ['groupId' => 'dm:'.$group->id]), ['device_id' => $device->device_id])
            ->assertNotFound();
    }

    public function test_fulfills_a_pending_join_request(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithClaimedGroup();
        MlsJoinRequest::create(['group_id' => $groupId, 'user_id' => $user->id, 'device_id' => $deviceId, 'status' => 'pending']);

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.joinRequest.fulfill', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertOk();

        $this->assertDatabaseHas('mls_join_requests', ['group_id' => $groupId, 'device_id' => $deviceId, 'status' => 'fulfilled']);
    }

    public function test_any_participant_can_fulfill_a_join_request(): void
    {
        [$creator, $groupId, $deviceId] = $this->participantWithClaimedGroup();
        MlsJoinRequest::create(['group_id' => $groupId, 'user_id' => $creator->id, 'device_id' => $deviceId, 'status' => 'pending']);

        $other = User::factory()->create();
        DirectMessageGroup::query()->findOrFail((int) str_replace('dm:', '', $groupId))
            ->participants()->attach($other->id);

        $this->actingAs($other)
            ->postJson(route('api.e2ee.mls.groups.joinRequest.fulfill', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertOk();

        $this->assertDatabaseHas('mls_join_requests', ['group_id' => $groupId, 'device_id' => $deviceId, 'status' => 'fulfilled']);
    }

    public function test_non_participant_cannot_fulfill_a_join_request(): void
    {
        [$creator, $groupId, $deviceId] = $this->participantWithClaimedGroup();
        MlsJoinRequest::create(['group_id' => $groupId, 'user_id' => $creator->id, 'device_id' => $deviceId, 'status' => 'pending']);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson(route('api.e2ee.mls.groups.joinRequest.fulfill', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertForbidden();

        $this->assertDatabaseHas('mls_join_requests', ['group_id' => $groupId, 'device_id' => $deviceId, 'status' => 'pending']);
    }

    public function test_lists_pending_join_requests_for_participants(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithClaimedGroup();
        MlsJoinRequest::create(['group_id' => $groupId, 'user_id' => $user->id, 'device_id' => $deviceId, 'status' => 'pending']);
        MlsJoinRequest::create(['group_id' => $groupId, 'user_id' => $user->id, 'device_id' => $this->uuid(), 'status' => 'fulfilled']);

        $this->actingAs($user)
            ->getJson(route('api.e2ee.mls.groups.joinRequests.pending', ['groupId' => $groupId]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', $deviceId);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->getJson(route('api.e2ee.mls.groups.joinRequests.pending', ['groupId' => $groupId]))
            ->assertForbidden();
    }

    public function test_join_request_broadcasts_to_all_dm_participants(): void
    {
        Event::fake([MlsJoinRequested::class]);

        [$creator, $groupId, $deviceId] = $this->participantWithClaimedGroup();
        $peer = User::factory()->create();
        DirectMessageGroup::query()->findOrFail((int) str_replace('dm:', '', $groupId))
            ->participants()->attach($peer->id);

        $this->actingAs($creator)
            ->postJson(route('api.e2ee.mls.groups.joinRequest', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertCreated();

        Event::assertDispatched(MlsJoinRequested::class, function (MlsJoinRequested $event) use ($creator, $peer, $deviceId) {
            sort($event->recipientUserIds);

            return $event->recipientUserIds === collect([$creator->id, $peer->id])->sort()->values()->all()
                && $event->requesterDeviceId === $deviceId;
        });
    }

    public function test_fulfilling_without_a_pending_request_returns_404(): void
    {
        [$user, $groupId, $deviceId] = $this->participantWithClaimedGroup();

        $this->actingAs($user)
            ->postJson(route('api.e2ee.mls.groups.joinRequest.fulfill', ['groupId' => $groupId]), ['device_id' => $deviceId])
            ->assertNotFound();
    }
}
