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

class ThreadPaginationTest extends TestCase
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
     * @return array{0: User, 1: Channel, 2: Thread, 3: array<int, Message>}
     */
    private function seedThreadReplies(int $count = 60): array
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $parent = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
        ]);
        $thread = Thread::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'message_id' => $parent->id,
        ]);

        $messages = [];
        for ($i = 0; $i < $count; $i++) {
            $messages[] = Message::factory()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'thread_id' => $thread->id,
                'created_at' => now()->subMinutes($count - $i),
            ]);
        }

        return [$user, $channel, $thread, $messages];
    }

    public function test_no_args_returns_latest_50_in_asc_order(): void
    {
        [$user, $channel, $thread, $messages] = $this->seedThreadReplies(60);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread])
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(50, $data);
        $this->assertSame((string) $messages[10]->id, $data[0]['id']);
        $this->assertSame((string) $messages[59]->id, $data[49]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertFalse($response->json('meta.has_more_after'));
        $this->assertSame((string) $messages[10]->id, $response->json('meta.oldest_id'));
        $this->assertSame((string) $messages[59]->id, $response->json('meta.newest_id'));
    }

    public function test_no_args_with_few_messages_has_no_more_before(): void
    {
        [$user, $channel, $thread] = $this->seedThreadReplies(10);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread])
        );

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertFalse($response->json('meta.has_more_before'));
        $this->assertFalse($response->json('meta.has_more_after'));
    }

    public function test_before_returns_messages_with_id_lt_anchor_in_asc_order(): void
    {
        [$user, $channel, $thread, $messages] = $this->seedThreadReplies(120);

        $anchor = $messages[100];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?before='.$anchor->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(50, $data);
        $this->assertSame((string) $messages[50]->id, $data[0]['id']);
        $this->assertSame((string) $messages[99]->id, $data[49]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertTrue($response->json('meta.has_more_after'));
    }

    public function test_after_returns_messages_with_id_gt_anchor_in_asc_order(): void
    {
        [$user, $channel, $thread, $messages] = $this->seedThreadReplies(120);

        $anchor = $messages[10];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?after='.$anchor->id
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(50, $data);
        $this->assertSame((string) $messages[11]->id, $data[0]['id']);
        $this->assertSame((string) $messages[60]->id, $data[49]['id']);

        $this->assertTrue($response->json('meta.has_more_before'));
        $this->assertTrue($response->json('meta.has_more_after'));
    }

    public function test_after_drains_to_tail_clears_has_more_after(): void
    {
        [$user, $channel, $thread, $messages] = $this->seedThreadReplies(60);

        $anchor = $messages[40];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?after='.$anchor->id
        );

        $response->assertOk();
        $this->assertCount(19, $response->json('data'));
        $this->assertFalse($response->json('meta.has_more_after'));
        $this->assertTrue($response->json('meta.has_more_before'));
    }

    public function test_limit_is_respected(): void
    {
        [$user, $channel, $thread, $messages] = $this->seedThreadReplies(60);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?limit=10'
        );

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertSame((string) $messages[59]->id, $response->json('meta.newest_id'));
        $this->assertSame((string) $messages[50]->id, $response->json('meta.oldest_id'));
    }

    public function test_limit_clamped_to_max_100(): void
    {
        [$user, $channel, $thread] = $this->seedThreadReplies(5);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?limit=500'
        );

        $response->assertStatus(422);
    }

    public function test_around_returns_balanced_window_with_target_in_middle(): void
    {
        [$user, $channel, $thread, $messages] = $this->seedThreadReplies(60);

        $target = $messages[30];

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?around='.$target->id
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

    public function test_around_nonexistent_id_returns_404(): void
    {
        [$user, $channel, $thread] = $this->seedThreadReplies(5);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?around=99999'
        );

        $response->assertNotFound();
    }

    public function test_around_message_from_other_thread_returns_404(): void
    {
        [$user, $channel, $thread] = $this->seedThreadReplies(5);

        $otherParent = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
        ]);
        $otherThread = Thread::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'message_id' => $otherParent->id,
        ]);
        $otherMessage = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'thread_id' => $otherThread->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('api.channels.threads.messages', [$channel, $thread]).'?around='.$otherMessage->id
        );

        $response->assertNotFound();
    }

    public function test_without_auth_is_unauthorized(): void
    {
        [, $channel, $thread] = $this->seedThreadReplies(5);

        $response = $this->getJson(
            route('api.channels.threads.messages', [$channel, $thread])
        );

        $response->assertUnauthorized();
    }
}
