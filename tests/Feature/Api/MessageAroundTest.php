<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageAroundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });
    }

    public function test_around_returns_balanced_window_with_target_in_middle(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $messages = [];
        for ($i = 0; $i < 60; $i++) {
            $messages[] = Message::factory()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'created_at' => now()->subMinutes(60 - $i),
            ]);
        }

        $target = $messages[30];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?around='.$target->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(51, $data); // 25 before + target + 25 after
        $this->assertSame((string) $target->id, $data[25]['id']);
        $this->assertSame((string) $messages[5]->id, $data[0]['id']);
        $this->assertSame((string) $messages[55]->id, $data[50]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertTrue($response->json('meta.has_more_after'));
        $this->assertSame((string) $messages[5]->id, $response->json('meta.oldest_id'));
        $this->assertSame((string) $messages[55]->id, $response->json('meta.newest_id'));
    }

    public function test_around_near_start_returns_partial_before(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $messages = [];
        for ($i = 0; $i < 40; $i++) {
            $messages[] = Message::factory()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'created_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $target = $messages[2];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?around='.$target->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2 + 1 + 25, $data);
        $this->assertSame((string) $target->id, $data[2]['id']);
        $this->assertFalse($response->json('meta.has_more_before'));
        $this->assertTrue($response->json('meta.has_more_after'));
    }

    public function test_around_near_end_returns_partial_after(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $messages = [];
        for ($i = 0; $i < 30; $i++) {
            $messages[] = Message::factory()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'created_at' => now()->subMinutes(30 - $i),
            ]);
        }

        $target = $messages[28];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?around='.$target->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(25 + 1 + 1, $data);
        $this->assertFalse($response->json('meta.has_more_after'));
        $this->assertTrue($response->json('meta.has_more_before'));
    }

    public function test_around_nonexistent_id_returns_404(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?around=99999'
        );

        $response->assertNotFound();
    }

    public function test_around_message_from_other_channel_returns_404(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $otherChannel = Channel::factory()->create();

        $otherMessage = Message::factory()->create([
            'channel_id' => $otherChannel->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?around='.$otherMessage->id
        );

        $response->assertNotFound();
    }

    public function test_around_without_auth_is_unauthorized(): void
    {
        $channel = Channel::factory()->create();
        $message = Message::factory()->create(['channel_id' => $channel->id]);

        $response = $this->getJson(
            route('api.channels.messages.index', $channel).'?around='.$message->id
        );

        $response->assertUnauthorized();
    }

    public function test_listing_without_around_still_works(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        Message::factory()->count(5)->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel)
        );

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }
}
