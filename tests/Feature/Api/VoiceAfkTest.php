<?php

namespace Tests\Feature\Api;

use App\Enums\ChannelType;
use App\Events\VoiceChannelJoined;
use App\Events\VoiceChannelLeft;
use App\Models\Channel;
use App\Models\ServerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VoiceAfkTest extends TestCase
{
    use RefreshDatabase;

    private function configureAfkChannel(): Channel
    {
        $channel = Channel::factory()->create(['type' => ChannelType::Voice]);
        $settings = ServerSetting::instance();
        $settings->afk_channel_id = $channel->id;
        $settings->save();

        return $channel;
    }

    public function test_park_writes_presence_and_broadcasts_joined(): void
    {
        Event::fake([VoiceChannelJoined::class, VoiceChannelLeft::class]);

        $channel = $this->configureAfkChannel();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.voice.afk.park'))
            ->assertOk()
            ->assertJsonPath('data.channel_id', $channel->id);

        $participants = Cache::get("voice_channel:{$channel->id}:participants", []);
        $this->assertArrayHasKey($user->id, $participants);
        $this->assertSame($user->id, $participants[$user->id]['id']);

        Event::assertDispatched(VoiceChannelJoined::class);
    }

    public function test_unpark_removes_presence_and_broadcasts_left(): void
    {
        Event::fake([VoiceChannelJoined::class, VoiceChannelLeft::class]);

        $channel = $this->configureAfkChannel();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.voice.afk.park'))->assertOk();

        $this->actingAs($user)
            ->deleteJson(route('api.voice.afk.unpark'))
            ->assertOk()
            ->assertJsonPath('data.channel_id', $channel->id);

        $this->assertEmpty(Cache::get("voice_channel:{$channel->id}:participants", []));
        Event::assertDispatched(VoiceChannelLeft::class);
    }

    public function test_park_is_a_noop_when_no_afk_channel_configured(): void
    {
        Event::fake([VoiceChannelJoined::class, VoiceChannelLeft::class]);

        $settings = ServerSetting::instance();
        $settings->afk_channel_id = null;
        $settings->save();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.voice.afk.park'))
            ->assertNoContent();

        Event::assertNotDispatched(VoiceChannelJoined::class);
    }
}
