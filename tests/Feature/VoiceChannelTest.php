<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Models\Category;
use App\Models\Channel;
use App\Models\User;
use App\Services\LiveKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class VoiceChannelTest extends TestCase
{
    use RefreshDatabase;

    private function createVoiceChannel(): Channel
    {
        $category = Category::factory()->create();

        return Channel::factory()->voice()->create([
            'category_id' => $category->id,
        ]);
    }

    private function createTextChannel(): Channel
    {
        $category = Category::factory()->create();

        return Channel::factory()->create([
            'category_id' => $category->id,
        ]);
    }

    private function mockLiveKitService(): void
    {
        $mock = Mockery::mock(LiveKitService::class);
        $mock->shouldReceive('generateToken')
            ->andReturn('test-jwt-token');
        $mock->shouldReceive('getServerUrl')
            ->andReturn('ws://localhost:7880');

        $this->app->instance(LiveKitService::class, $mock);
    }

    public function test_guests_cannot_join_voice_channels(): void
    {
        $channel = $this->createVoiceChannel();

        $response = $this->postJson(route('channels.voice.join', $channel));

        $response->assertUnauthorized();
    }

    public function test_authenticated_users_can_join_voice_channels(): void
    {
        $this->mockLiveKitService();

        $user = User::factory()->create();
        $channel = $this->createVoiceChannel();

        $response = $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel));

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'url',
            'room',
            'channel_id',
            'channel_name',
        ]);
        $response->assertJson([
            'token' => 'test-jwt-token',
            'url' => 'ws://localhost:7880',
            'room' => "voice-channel-{$channel->id}",
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
        ]);
    }

    public function test_users_cannot_join_text_channel_as_voice(): void
    {
        $this->mockLiveKitService();

        $user = User::factory()->create();
        $channel = $this->createTextChannel();

        $response = $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel));

        $response->assertForbidden();
    }

    public function test_authenticated_users_can_leave_voice_channels(): void
    {
        $user = User::factory()->create();
        $channel = $this->createVoiceChannel();

        $response = $this->actingAs($user)
            ->postJson(route('channels.voice.leave', $channel));

        $response->assertOk();
        $response->assertJson([
            'channel_id' => $channel->id,
        ]);
    }

    public function test_guests_cannot_leave_voice_channels(): void
    {
        $channel = $this->createVoiceChannel();

        $response = $this->postJson(route('channels.voice.leave', $channel));

        $response->assertUnauthorized();
    }

    public function test_voice_channel_join_response_contains_correct_room_name(): void
    {
        $this->mockLiveKitService();

        $user = User::factory()->create();
        $channel = $this->createVoiceChannel();

        $response = $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel));

        $response->assertOk();
        $this->assertEquals("voice-channel-{$channel->id}", $response->json('room'));
    }

    public function test_voice_channel_has_correct_type(): void
    {
        $channel = $this->createVoiceChannel();

        $this->assertEquals(ChannelType::Voice, $channel->type);
    }

    public function test_text_channel_has_correct_type(): void
    {
        $channel = $this->createTextChannel();

        $this->assertEquals(ChannelType::Text, $channel->type);
    }

    public function test_voice_channels_appear_in_chat_categories(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Channel::factory()->create(['category_id' => $category->id, 'type' => 'text']);
        Channel::factory()->voice()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->get(route('chat'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Chat')
            ->has('categories', 1)
            ->has('categories.0.channels', 2)
        );
    }

    public function test_rejoining_voice_channel_is_idempotent(): void
    {
        $this->mockLiveKitService();

        $user = User::factory()->create();
        $channel = $this->createVoiceChannel();
        $cacheKey = "voice_channel:{$channel->id}:participants";

        // First join
        $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel))
            ->assertOk();

        $participantsAfterFirstJoin = Cache::get($cacheKey, []);
        $this->assertCount(1, $participantsAfterFirstJoin);
        $this->assertArrayHasKey($user->id, $participantsAfterFirstJoin);

        // Second join (simulates reconnect after page refresh)
        $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel))
            ->assertOk();

        $participantsAfterRejoin = Cache::get($cacheKey, []);
        $this->assertCount(1, $participantsAfterRejoin);
        $this->assertArrayHasKey($user->id, $participantsAfterRejoin);
    }

    public function test_leaving_after_rejoin_removes_participant_from_cache(): void
    {
        $this->mockLiveKitService();

        $user = User::factory()->create();
        $channel = $this->createVoiceChannel();
        $cacheKey = "voice_channel:{$channel->id}:participants";

        // Join twice (simulate refresh reconnect)
        $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel));
        $this->actingAs($user)
            ->postJson(route('channels.voice.join', $channel));

        // Leave
        $this->actingAs($user)
            ->postJson(route('channels.voice.leave', $channel))
            ->assertOk();

        $participants = Cache::get($cacheKey, []);
        $this->assertCount(0, $participants);
        $this->assertArrayNotHasKey($user->id, $participants);
    }
}
