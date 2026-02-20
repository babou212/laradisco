<?php

namespace Tests\Feature;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_presence(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/presence', [
            'status' => 'online',
            'custom_status' => 'Available for chat!',
        ]);

        $response->assertRedirect();

        // Only custom_status is stored in DB - status is managed by WebSocket presence
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'custom_status' => 'Available for chat!',
        ]);
    }

    public function test_updating_presence_broadcasts_event(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/presence', [
            'status' => 'dnd',
            'custom_status' => 'In a meeting',
        ]);

        Event::assertDispatched(UserPresenceUpdated::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->status === UserStatusType::DoNotDisturb
                && $event->customStatus === 'In a meeting';
        });
    }

    public function test_guest_cannot_update_presence(): void
    {
        $response = $this->postJson('/presence', [
            'status' => 'online',
        ]);

        $response->assertUnauthorized();
    }

    public function test_presence_update_requires_valid_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/presence', [
            'status' => 'invalid-status',
        ]);

        $response->assertUnprocessable();
    }

    public function test_custom_status_can_be_null(): void
    {
        $user = User::factory()->create([
            'custom_status' => 'Old status',
        ]);

        $response = $this->actingAs($user)->postJson('/presence', [
            'status' => 'online',
            'custom_status' => null,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'custom_status' => null,
        ]);
    }

    public function test_middleware_updates_last_seen_at(): void
    {
        $user = User::factory()->create([
            'last_seen_at' => now()->subHours(2),
        ]);

        $originalLastSeen = $user->last_seen_at;

        $this->actingAs($user)->get('/dashboard');

        $user->refresh();

        $this->assertNotEquals($originalLastSeen, $user->last_seen_at);
        $this->assertTrue($user->last_seen_at->greaterThan($originalLastSeen));
    }

    public function test_middleware_does_not_broadcast_presence(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'last_seen_at' => now()->subHours(2),
        ]);

        $this->actingAs($user)->get('/dashboard');

        // The middleware only updates last_seen_at; presence is
        // entirely managed by the WebSocket presence channel.
        Event::assertNotDispatched(UserPresenceUpdated::class);
    }

    public function test_login_resets_user_status_to_online(): void
    {
        $user = User::factory()->create([
            'status' => 'offline',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $user->refresh();

        $this->assertSame('online', $user->status);
    }

    public function test_login_resets_dnd_status_to_online(): void
    {
        $user = User::factory()->create([
            'status' => 'dnd',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $user->refresh();

        $this->assertSame('online', $user->status);
    }

    public function test_presence_event_broadcasts_immediately(): void
    {
        $event = new UserPresenceUpdated(
            User::factory()->create(),
            UserStatusType::Online,
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class, $event);
    }

    public function test_presence_event_has_correct_broadcast_name(): void
    {
        $event = new UserPresenceUpdated(
            User::factory()->create(),
            UserStatusType::Online,
        );

        $this->assertSame('user.presence.updated', $event->broadcastAs());
    }

    public function test_presence_event_broadcasts_on_online_channel(): void
    {
        $event = new UserPresenceUpdated(
            User::factory()->create(),
            UserStatusType::Online,
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(\Illuminate\Broadcasting\PresenceChannel::class, $channels[0]);
        $this->assertSame('presence-online', $channels[0]->name);
    }

    public function test_members_shared_prop_includes_all_users(): void
    {
        $userA = User::factory()->create(['username' => 'alpha']);
        $userB = User::factory()->create(['username' => 'beta']);
        $userC = User::factory()->create(['username' => 'gamma']);

        $response = $this->actingAs($userA)->get('/');

        $response->assertInertia(function ($page) use ($userA, $userB, $userC) {
            $page->has('members', 3);
            $members = collect($page->toArray()['props']['members']);
            $this->assertTrue($members->contains('id', $userA->id));
            $this->assertTrue($members->contains('id', $userB->id));
            $this->assertTrue($members->contains('id', $userC->id));
        });
    }

    public function test_members_shared_prop_contains_expected_fields(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'nickname' => 'Tester',
            'custom_status' => 'Hello!',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertInertia(function ($page) use ($user) {
            $members = collect($page->toArray()['props']['members']);
            $member = $members->firstWhere('id', $user->id);

            $this->assertNotNull($member);
            $this->assertSame($user->username, $member['username']);
            $this->assertSame($user->display_name, $member['display_name']);
            $this->assertSame('Hello!', $member['custom_status']);
            $this->assertArrayHasKey('avatar_path', $member);
        });
    }

    public function test_members_shared_prop_not_available_for_guests(): void
    {
        User::factory()->create();

        $response = $this->get('/login');

        $response->assertInertia(function ($page) {
            $members = $page->toArray()['props']['members'] ?? [];
            $this->assertEmpty($members);
        });
    }
}
