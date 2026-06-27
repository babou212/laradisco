<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\ThreadReplyNotification;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class ThreadReplyNotificationTest extends TestCase
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

    private function reply(User $user, Channel $channel, Message $parent, array $payload = [])
    {
        return $this->actingAs($user)->postJson(
            route('api.channels.messages.thread.store', [$channel, $parent]),
            array_merge(['content' => 'a reply'], $payload),
        );
    }

    public function test_followers_are_notified_when_someone_replies(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $follower = User::factory()->create();
        $poster = User::factory()->create();
        $channel = Channel::factory()->create();
        $parent = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $author->id]);

        // $author starts the thread (follows it), $follower joins by replying.
        $this->reply($author, $channel, $parent)->assertCreated();
        $this->reply($follower, $channel, $parent)->assertCreated();

        Notification::fake(); // reset so we only assert on the next reply

        // A third user replies -> author + follower are notified, poster is not.
        $this->reply($poster, $channel, $parent)->assertCreated();

        Notification::assertSentTo($author, ThreadReplyNotification::class);
        Notification::assertSentTo($follower, ThreadReplyNotification::class);
        Notification::assertNotSentTo($poster, ThreadReplyNotification::class);
    }

    public function test_mentioned_follower_is_not_double_notified(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $poster = User::factory()->create();
        $channel = Channel::factory()->create();
        $parent = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $author->id]);

        // $author starts and follows the thread.
        $this->reply($author, $channel, $parent)->assertCreated();

        Notification::fake();

        // $poster replies and @mentions the follower ($author): mention wins.
        $this->reply($poster, $channel, $parent, ['mention_user_ids' => [$author->id]])->assertCreated();

        Notification::assertSentTo($author, MentionNotification::class);
        Notification::assertNotSentTo($author, ThreadReplyNotification::class);
    }
}
