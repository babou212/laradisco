<?php

namespace Tests\Feature;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Covers the two-stage, heartbeat-driven presence model:
 *  - a fresh heartbeat keeps a user online,
 *  - a 45s lapse downgrades them to idle, a 105s lapse to offline,
 *  - and a returning heartbeat revives them to online.
 */
class PresenceHeartbeatTest extends TestCase
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

    private function statusOf(int $userId): ?string
    {
        foreach ($this->presence->getOnlineUsers() as $entry) {
            if ((int) $entry['id'] === $userId) {
                return $entry['status'];
            }
        }

        return null;
    }

    public function test_fresh_heartbeat_marks_user_online(): void
    {
        $user = User::factory()->create();

        $transition = $this->presence->heartbeat($user);

        $this->assertSame(UserStatusType::Online, $transition);
        $this->assertSame('online', $this->statusOf($user->id));
    }

    public function test_user_goes_idle_then_offline_as_heartbeats_lapse(): void
    {
        Event::fake([UserPresenceUpdated::class]);

        $user = User::factory()->create();
        $this->presence->heartbeat($user);

        // 50s without a beat: past the 45s idle grace, before the 105s offline cutoff.
        // Drive the sweep through the console command so its transition broadcast runs.
        $this->travel(50)->seconds();
        $this->artisan('presence:sweep')->assertSuccessful();

        $this->assertSame('idle', $this->statusOf($user->id));
        Event::assertDispatched(
            UserPresenceUpdated::class,
            fn (UserPresenceUpdated $e) => $e->user->id === $user->id && $e->status === UserStatusType::Idle,
        );

        // 110s total without a beat: past the offline cutoff.
        $this->travel(60)->seconds();
        $this->artisan('presence:sweep')->assertSuccessful();

        $this->assertNull($this->statusOf($user->id));
        $this->assertEmpty($this->presence->getOnlineUsers());
        Event::assertDispatched(
            UserPresenceUpdated::class,
            fn (UserPresenceUpdated $e) => $e->user->id === $user->id && $e->status === UserStatusType::Offline,
        );
    }

    public function test_heartbeat_restores_idle_user_to_online(): void
    {
        $user = User::factory()->create();
        $this->presence->heartbeat($user);

        $this->travel(50)->seconds();
        $this->presence->sweep();
        $this->assertSame('idle', $this->statusOf($user->id));

        // A returning heartbeat reports the online transition and clears idle.
        $transition = $this->presence->heartbeat($user);

        $this->assertSame(UserStatusType::Online, $transition);
        $this->assertSame('online', $this->statusOf($user->id));
    }

    public function test_repeat_heartbeat_while_online_reports_no_transition(): void
    {
        $user = User::factory()->create();
        $this->presence->heartbeat($user);

        // Still well within the idle grace window: nothing to broadcast.
        $this->travel(20)->seconds();
        $this->assertNull($this->presence->heartbeat($user));
        $this->assertSame('online', $this->statusOf($user->id));
    }

    public function test_heartbeat_endpoint_revives_a_swept_user(): void
    {
        Event::fake([UserPresenceUpdated::class]);

        $user = User::factory()->create();
        $this->presence->heartbeat($user);

        // Let the user age out completely, then sweep them away.
        $this->travel(110)->seconds();
        $this->presence->sweep();
        $this->assertEmpty($this->presence->getOnlineUsers());

        $this->actingAs($user)
            ->postJson('/api/v1/presence/heartbeat')
            ->assertOk();

        $this->assertSame('online', $this->statusOf($user->id));
        Event::assertDispatched(
            UserPresenceUpdated::class,
            fn (UserPresenceUpdated $e) => $e->user->id === $user->id && $e->status === UserStatusType::Online,
        );
    }
}
