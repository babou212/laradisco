<?php

namespace Tests\Feature\Api;

use App\Events\VoiceChannelJoined;
use App\Events\VoiceChannelLeft;
use App\Events\VoiceKeyRotated;
use App\Models\Channel;
use App\Models\User;
use App\Services\LiveKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livekit\ParticipantInfo;
use Livekit\Room;
use Livekit\WebhookEvent;
use Mockery;
use Tests\TestCase;

class LiveKitWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function webhookEvent(string $event, Channel $channel, ?User $user, string $sid = 'PA_test'): WebhookEvent
    {
        $payload = new WebhookEvent;
        $payload->setEvent($event);

        $room = new Room;
        $room->setName("voice-channel-{$channel->id}");
        $payload->setRoom($room);

        if ($user) {
            $participant = new ParticipantInfo;
            $participant->setIdentity((string) $user->id);
            $participant->setSid($sid);
            $payload->setParticipant($participant);
        }

        return $payload;
    }

    private function mockReceiver(WebhookEvent $event): void
    {
        $this->mock(LiveKitService::class, function ($mock) use ($event): void {
            $mock->makePartial();
            $mock->shouldReceive('receiveWebhook')->once()->andReturn($event);
        });
    }

    public function test_participant_joined_adds_to_cache_and_broadcasts(): void
    {
        Event::fake([VoiceChannelJoined::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->voice()->create();

        $this->mockReceiver($this->webhookEvent('participant_joined', $channel, $user));

        $this->postJson(route('api.livekit.webhook'), [], ['Authorization' => 'signed'])
            ->assertOk()
            ->assertJson(['received' => true]);

        $cached = Cache::get("voice_channel:{$channel->id}:participants");
        $this->assertArrayHasKey($user->id, $cached);
        $this->assertSame($user->id, $cached[$user->id]['id']);
        $this->assertSame('PA_test', $cached[$user->id]['_sid']);

        Event::assertDispatched(VoiceChannelJoined::class, fn ($e) => $e->channel->id === $channel->id && $e->user->id === $user->id);
    }

    public function test_participant_left_removes_from_cache_and_broadcasts(): void
    {
        Event::fake([VoiceChannelLeft::class, VoiceKeyRotated::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $channel = Channel::factory()->voice()->create();

        Cache::put("voice_channel:{$channel->id}:participants", [
            $user->id => ['id' => $user->id, 'username' => $user->username, '_sid' => 'PA_test'],
            $other->id => ['id' => $other->id, 'username' => $other->username, '_sid' => 'PA_other'],
        ]);

        $this->mockReceiver($this->webhookEvent('participant_left', $channel, $user));

        $this->postJson(route('api.livekit.webhook'), [], ['Authorization' => 'signed'])
            ->assertOk();

        $cached = Cache::get("voice_channel:{$channel->id}:participants");
        $this->assertArrayNotHasKey($user->id, $cached);
        $this->assertArrayHasKey($other->id, $cached);

        Event::assertDispatched(VoiceChannelLeft::class, fn ($e) => $e->user->id === $user->id);

        // The shared E2EE key is rotated for the remaining members so the leaver
        // can no longer decrypt new audio.
        Event::assertDispatched(
            VoiceKeyRotated::class,
            fn ($e) => $e->channel->id === $channel->id && $e->keyIndex === 1
        );
        $this->assertSame(1, Cache::get("voice_channel:{$channel->id}:e2ee")['index']);
    }

    public function test_stale_leave_from_superseded_session_is_ignored(): void
    {
        Event::fake([VoiceChannelLeft::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->voice()->create();

        // The user is currently connected with a newer session (PA_new); the
        // incoming leave is for the old, already-kicked session (PA_old).
        Cache::put("voice_channel:{$channel->id}:participants", [
            $user->id => ['id' => $user->id, 'username' => $user->username, '_sid' => 'PA_new'],
        ]);

        $this->mockReceiver($this->webhookEvent('participant_left', $channel, $user, 'PA_old'));

        $this->postJson(route('api.livekit.webhook'), [], ['Authorization' => 'signed'])
            ->assertOk();

        $cached = Cache::get("voice_channel:{$channel->id}:participants");
        $this->assertArrayHasKey($user->id, $cached);

        Event::assertNotDispatched(VoiceChannelLeft::class);
    }

    public function test_room_finished_clears_all_presence(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->voice()->create();

        Cache::put("voice_channel:{$channel->id}:participants", [
            $user->id => ['id' => $user->id, 'username' => $user->username, '_sid' => 'PA_test'],
        ]);
        Cache::put("voice_channel:{$channel->id}:e2ee", ['key' => 'deadbeef', 'index' => 0]);

        $this->mockReceiver($this->webhookEvent('room_finished', $channel, null));

        $this->postJson(route('api.livekit.webhook'), [], ['Authorization' => 'signed'])
            ->assertOk();

        $this->assertNull(Cache::get("voice_channel:{$channel->id}:participants"));
        $this->assertNull(Cache::get("voice_channel:{$channel->id}:e2ee"));
    }

    public function test_unverifiable_webhook_is_rejected(): void
    {
        config([
            'livekit.api_key' => 'test-key',
            'livekit.api_secret' => 'test-secret-long-enough-for-hs256-signing-here',
        ]);

        // No (valid) Authorization header — the real receiver must reject it.
        $this->postJson(route('api.livekit.webhook'), ['event' => 'participant_joined'])
            ->assertStatus(401);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
