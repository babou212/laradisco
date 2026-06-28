<?php

namespace Tests\Feature\Api;

use App\Events\UserBanned;
use App\Models\Ban;
use App\Models\Channel;
use App\Models\User;
use App\Services\LiveKitService;
use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ModerationBanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Swap LiveKitService for a partial mock so removeParticipant never reaches a
     * real LiveKit server. Returns the actor used to issue bans.
     */
    private function mockLiveKit(?\Closure $expectations = null): void
    {
        $this->mock(LiveKitService::class, function ($mock) use ($expectations): void {
            $mock->makePartial();
            if ($expectations) {
                $expectations($mock);
            } else {
                $mock->shouldReceive('removeParticipant')->andReturnNull();
            }
        });
    }

    private function moderation(): ModerationService
    {
        return app(ModerationService::class);
    }

    public function test_ban_revokes_all_target_tokens(): void
    {
        $this->mockLiveKit();

        $admin = User::factory()->create();
        $target = User::factory()->create();
        $target->createToken('Desktop');
        $target->createToken('Mobile');

        $this->assertSame(2, $target->tokens()->count());

        $this->moderation()->ban($target, $admin);

        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_ban_dispatches_user_banned_event_with_details(): void
    {
        Event::fake([UserBanned::class]);
        $this->mockLiveKit();

        $admin = User::factory()->create();
        $target = User::factory()->create();
        $expiresAt = now()->addDays(3);

        $this->moderation()->ban($target, $admin, 'Spamming', $expiresAt);

        Event::assertDispatched(
            UserBanned::class,
            fn (UserBanned $e) => $e->user->id === $target->id
                && $e->reason === 'Spamming'
                && $e->expiresAt?->format('Y-m-d') === $expiresAt->format('Y-m-d')
        );
    }

    public function test_ban_removes_target_only_from_voice_channels_they_are_in(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $other = User::factory()->create();

        $occupied = Channel::factory()->voice()->create();
        $empty = Channel::factory()->voice()->create();

        Cache::put("voice_channel:{$occupied->id}:participants", [
            $target->id => ['id' => $target->id, 'username' => $target->username, '_sid' => 'PA_target'],
        ]);
        // The target is not in this room — only another user is.
        Cache::put("voice_channel:{$empty->id}:participants", [
            $other->id => ['id' => $other->id, 'username' => $other->username, '_sid' => 'PA_other'],
        ]);

        $this->mockLiveKit(function ($mock) use ($occupied, $target): void {
            $mock->shouldReceive('removeParticipant')
                ->once()
                ->with("voice-channel-{$occupied->id}", (string) $target->id)
                ->andReturnNull();
        });

        $this->moderation()->ban($target, $admin);

        // Mockery's ->once() expectation is verified on tearDown.
        $this->assertTrue(true);
    }

    public function test_ban_persists_and_revokes_tokens_when_livekit_throws(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $target->createToken('Desktop');

        $channel = Channel::factory()->voice()->create();
        Cache::put("voice_channel:{$channel->id}:participants", [
            $target->id => ['id' => $target->id, 'username' => $target->username, '_sid' => 'PA_target'],
        ]);

        $this->mockLiveKit(function ($mock): void {
            $mock->shouldReceive('removeParticipant')
                ->once()
                ->andThrow(new \RuntimeException('LiveKit unreachable'));
        });

        $ban = $this->moderation()->ban($target, $admin);

        $this->assertDatabaseHas('bans', ['id' => $ban->id, 'user_id' => $target->id]);
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_login_is_blocked_for_temporarily_banned_user(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        Ban::create([
            'user_id' => $user->id,
            'banned_by' => $admin->id,
            'reason' => 'Breaking rules',
            'expires_at' => now()->addDays(2),
        ]);

        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Desktop',
        ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'account_banned',
                'reason' => 'Breaking rules',
                'permanent' => false,
            ])
            ->assertJsonPath('expires_at', fn ($v) => is_string($v) && $v !== '');
    }

    public function test_login_is_blocked_for_permanently_banned_user(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        Ban::create([
            'user_id' => $user->id,
            'banned_by' => $admin->id,
            'reason' => null,
            'expires_at' => null,
        ]);

        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Desktop',
        ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'account_banned',
                'permanent' => true,
            ])
            ->assertJsonPath('expires_at', null);
    }

    public function test_non_banned_user_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Desktop',
        ])
            ->assertOk()
            ->assertJsonPath('data.token', fn ($v) => is_string($v) && $v !== '');
    }

    public function test_two_factor_challenge_is_blocked_for_banned_user(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        Ban::create([
            'user_id' => $user->id,
            'banned_by' => $admin->id,
            'reason' => 'Banned mid-challenge',
            'expires_at' => null,
        ]);

        $challengeToken = 'challenge-token-test';
        Cache::put("two_factor_challenge:{$challengeToken}", [
            'user_id' => $user->id,
            'device_name' => 'Desktop',
        ], now()->addMinutes(5));

        $this->postJson(route('api.auth.two-factor-challenge'), [
            'challenge_token' => $challengeToken,
            'code' => '123456',
        ])
            ->assertStatus(403)
            ->assertJson(['code' => 'account_banned']);
    }

    public function test_checkbanned_middleware_returns_structured_403(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        Ban::create([
            'user_id' => $user->id,
            'banned_by' => $admin->id,
            'reason' => 'No access',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(route('api.auth.me'))
            ->assertStatus(403)
            ->assertJson([
                'code' => 'account_banned',
                'reason' => 'No access',
                'permanent' => true,
            ]);
    }

    public function test_unban_restores_login(): void
    {
        $this->mockLiveKit();

        $admin = User::factory()->create();
        $user = User::factory()->create();

        $this->moderation()->ban($user, $admin, 'Temporary');

        // Banned: login rejected.
        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Desktop',
        ])->assertStatus(403);

        $this->moderation()->unban($user);

        // Unbanned: login succeeds with a fresh token, no lingering lockout.
        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Desktop',
        ])
            ->assertOk()
            ->assertJsonPath('data.token', fn ($v) => is_string($v) && $v !== '');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
