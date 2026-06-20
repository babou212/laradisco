<?php

namespace Tests\Feature\Api;

use App\Events\ThreadDeleted;
use App\Events\ThreadMessageDeleted;
use App\Events\ThreadUpdated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\TestCase;

class ThreadReplyDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
        });

        Event::fake([ThreadDeleted::class, ThreadUpdated::class, ThreadMessageDeleted::class]);
    }

    /**
     * @return array{0: User, 1: Channel, 2: Message, 3: Thread}
     */
    private function seedThread(int $replyCount): array
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
            'message_count' => $replyCount,
        ]);

        for ($i = 0; $i < $replyCount; $i++) {
            Message::factory()->create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'thread_id' => $thread->id,
                'created_at' => now()->subMinutes($replyCount - $i),
            ]);
        }

        return [$user, $channel, $parent, $thread];
    }

    public function test_deleting_last_reply_deletes_the_thread(): void
    {
        [$user, $channel, $parent, $thread] = $this->seedThread(1);
        $reply = $thread->messages()->first();
        $thread->followers()->attach($user->id);

        $response = $this->actingAs($user)->deleteJson(
            route('api.channels.threads.messages.destroy', [$channel, $thread, $reply])
        );

        $response->assertNoContent();

        // Thread and its dependants are gone; the parent message survives.
        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('thread_followers', ['thread_id' => $thread->id]);
        $this->assertDatabaseMissing('messages', ['id' => $reply->id]);
        $this->assertDatabaseHas('messages', ['id' => $parent->id, 'deleted_at' => null]);

        Event::assertDispatched(ThreadDeleted::class, function (ThreadDeleted $event) use ($parent, $thread, $channel) {
            return $event->messageId === $parent->id
                && $event->threadId === $thread->id
                && $event->channelId === $channel->id;
        });
        Event::assertNotDispatched(ThreadUpdated::class);
    }

    public function test_deleting_one_of_several_replies_keeps_the_thread(): void
    {
        [$user, $channel, , $thread] = $this->seedThread(2);
        $reply = $thread->messages()->latest()->first();

        $response = $this->actingAs($user)->deleteJson(
            route('api.channels.threads.messages.destroy', [$channel, $thread, $reply])
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('threads', ['id' => $thread->id]);
        $this->assertSame(1, $thread->fresh()->messages()->count());

        Event::assertDispatched(ThreadUpdated::class);
        Event::assertNotDispatched(ThreadDeleted::class);
    }
}
