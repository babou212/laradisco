<?php

namespace Tests\Feature;

use App\Events\UserActivityUpdated;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Covers live rich-presence activity: it rides the existing Redis presence
 * blob, is broadcast on the presence channel, has its `started_at` stamped
 * server-side, and is gated by the `show_activity` privacy flag.
 */
class PresenceActivityTest extends TestCase
{
    use RefreshDatabase;

    private const HASH_KEY = 'presence:online';

    private PresenceService $presence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presence = app(PresenceService::class);
        Redis::del(self::HASH_KEY);
    }

    protected function tearDown(): void
    {
        Redis::del(self::HASH_KEY);
        parent::tearDown();
    }

    /** @return array<string, mixed>|null */
    private function activityOf(int $userId): ?array
    {
        foreach ($this->presence->getOnlineUsers() as $entry) {
            if ((int) $entry['id'] === $userId) {
                return $entry['activity'] ?? null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'game',
            'name' => 'Counter-Strike 2',
            'application_id' => 'steam-730',
        ], $overrides);
    }

    public function test_update_activity_stores_in_registry_and_broadcasts(): void
    {
        Event::fake([UserActivityUpdated::class]);

        $user = User::factory()->create();
        $this->presence->heartbeat($user); // register in the online registry

        $this->actingAs($user)
            ->patchJson('/api/v1/presence/activity', ['activity' => $this->payload()])
            ->assertOk();

        $activity = $this->activityOf($user->id);
        $this->assertNotNull($activity);
        $this->assertSame('Counter-Strike 2', $activity['name']);
        $this->assertSame('steam-730', $activity['application_id']);
        $this->assertIsInt($activity['started_at']);

        Event::assertDispatched(
            UserActivityUpdated::class,
            fn (UserActivityUpdated $e) => $e->user->id === $user->id && $e->activity['name'] === 'Counter-Strike 2',
        );
    }

    public function test_started_at_is_preserved_when_unchanged_and_restamped_on_change(): void
    {
        $user = User::factory()->create();
        $this->presence->heartbeat($user);

        $this->presence->updateActivity($user, $this->payload());
        $first = $this->activityOf($user->id)['started_at'];

        // Same activity 30s later: the elapsed anchor must not move.
        $this->travel(30)->seconds();
        $this->presence->updateActivity($user, $this->payload());
        $this->assertSame($first, $this->activityOf($user->id)['started_at']);

        // A different activity restamps started_at to "now".
        $this->travel(30)->seconds();
        $this->presence->updateActivity($user, $this->payload([
            'name' => 'Dota 2',
            'application_id' => 'steam-570',
        ]));
        $this->assertNotSame($first, $this->activityOf($user->id)['started_at']);
    }

    public function test_null_activity_clears_it(): void
    {
        $user = User::factory()->create();
        $this->presence->heartbeat($user);

        $this->presence->updateActivity($user, $this->payload());
        $this->assertNotNull($this->activityOf($user->id));

        $this->actingAs($user)
            ->patchJson('/api/v1/presence/activity', ['activity' => null])
            ->assertOk();

        $this->assertNull($this->activityOf($user->id));
    }

    public function test_activity_is_discarded_when_sharing_is_disabled(): void
    {
        Event::fake([UserActivityUpdated::class]);

        $user = User::factory()->create(['show_activity' => false]);
        $this->presence->heartbeat($user);

        $this->actingAs($user)
            ->patchJson('/api/v1/presence/activity', ['activity' => $this->payload()])
            ->assertOk();

        $this->assertNull($this->activityOf($user->id));
        Event::assertDispatched(
            UserActivityUpdated::class,
            fn (UserActivityUpdated $e) => $e->user->id === $user->id && $e->activity === null,
        );
    }

    public function test_disabling_show_activity_clears_live_activity(): void
    {
        Event::fake([UserActivityUpdated::class]);

        $user = User::factory()->create(['show_activity' => true]);
        $this->presence->heartbeat($user);
        $this->presence->updateActivity($user, $this->payload());
        $this->assertNotNull($this->activityOf($user->id));

        $this->actingAs($user)
            ->patchJson('/api/v1/settings/profile', ['show_activity' => false])
            ->assertOk();

        $this->assertNull($this->activityOf($user->id));
        Event::assertDispatched(
            UserActivityUpdated::class,
            fn (UserActivityUpdated $e) => $e->user->id === $user->id && $e->activity === null,
        );
    }
}
