<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MessagePaginationTest extends TestCase
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

    /**
     * @return array{0: User, 1: Channel, 2: array<int, Message>}
     */
    private function seedMessages(int $count = 60): array
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $messages = [];
        for ($i = 0; $i < $count; $i++) {
            $messages[] = Message::factory()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'created_at' => now()->subMinutes($count - $i),
            ]);
        }

        return [$user, $channel, $messages];
    }

    public function test_no_args_returns_latest_20_in_asc_order(): void
    {
        [$user, $channel, $messages] = $this->seedMessages(60);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel)
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(20, $data);
        $this->assertSame((string) $messages[40]->id, $data[0]['id']);
        $this->assertSame((string) $messages[59]->id, $data[19]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertFalse($response->json('meta.has_more_after'));
        $this->assertSame((string) $messages[40]->id, $response->json('meta.oldest_id'));
        $this->assertSame((string) $messages[59]->id, $response->json('meta.newest_id'));
    }

    public function test_no_args_with_few_messages_has_no_more_before(): void
    {
        [$user, $channel, $messages] = $this->seedMessages(10);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel)
        );

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertFalse($response->json('meta.has_more_before'));
        $this->assertFalse($response->json('meta.has_more_after'));
    }

    public function test_before_returns_messages_with_id_lt_anchor_in_asc_order(): void
    {
        [$user, $channel, $messages] = $this->seedMessages(120);

        $anchor = $messages[100];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?before='.$anchor->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(20, $data);
        $this->assertSame((string) $messages[80]->id, $data[0]['id']);
        $this->assertSame((string) $messages[99]->id, $data[19]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertTrue($response->json('meta.has_more_after'));
    }

    public function test_after_returns_messages_with_id_gt_anchor_in_asc_order(): void
    {
        [$user, $channel, $messages] = $this->seedMessages(120);

        $anchor = $messages[10];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?after='.$anchor->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(20, $data);
        $this->assertSame((string) $messages[11]->id, $data[0]['id']);
        $this->assertSame((string) $messages[30]->id, $data[19]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertTrue($response->json('meta.has_more_after'));
    }

    public function test_after_drains_to_tail_clears_has_more_after(): void
    {
        [$user, $channel, $messages] = $this->seedMessages(60);

        $anchor = $messages[40];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?after='.$anchor->id
        );

        $response->assertOk();
        $this->assertCount(19, $response->json('data'));
        $this->assertFalse($response->json('meta.has_more_after'));
        $this->assertTrue($response->json('meta.has_more_before'));
    }

    public function test_limit_is_respected(): void
    {
        [$user, $channel, $messages] = $this->seedMessages(60);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?limit=10'
        );

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertSame((string) $messages[59]->id, $response->json('meta.newest_id'));
        $this->assertSame((string) $messages[50]->id, $response->json('meta.oldest_id'));
    }

    public function test_limit_clamped_to_max_100(): void
    {
        [$user, $channel] = $this->seedMessages(5);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel).'?limit=500'
        );

        $response->assertStatus(422);
    }

    public function test_thread_replies_excluded_from_listing(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $top = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);
        $thread = Thread::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'message_id' => $top->id,
        ]);
        Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.messages.index', $channel)
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame((string) $top->id, $response->json('data.0.id'));
    }
}
