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
}
