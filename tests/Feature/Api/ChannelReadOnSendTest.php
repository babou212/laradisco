<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Sending a message must advance the author's own read marker so the sidebar
 * never flags a channel as unread for the person who just posted in it.
 * (decorateUnread() computes has_unread = MAX(messages.created_at) > last_read_at.)
 */
class ChannelReadOnSendTest extends TestCase
{
    use RefreshDatabase;

    private function allowChannelAccess(): void
    {
        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
            $mock->shouldReceive('getUsersWithChannelAccess')->andReturn([]);
        });
    }

    /** @return array{last_read_at: ?string} */
    private function pivot(int $channelId, int $userId): ?object
    {
        return DB::table('channel_user')
            ->where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->first();
    }

    public function test_sending_a_message_marks_the_channel_read_for_the_author(): void
    {
        $this->allowChannelAccess();

        $author = User::factory()->create();
        $channel = Channel::factory()->create();

        $send = $this->actingAs($author)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'hello world'],
        )->assertCreated();

        $message = Message::query()->findOrFail((int) $send->json('data.id'));

        $pivot = $this->pivot($channel->id, $author->id);
        $this->assertNotNull($pivot, 'A read marker row should exist for the author.');
        $this->assertNotNull($pivot->last_read_at, 'last_read_at should be set on send.');

        // The marker must be at least as new as the author's own message, so the
        // unread comparison (latest_at > last_read_at) is false for the author.
        $this->assertGreaterThanOrEqual(
            strtotime((string) $message->created_at),
            strtotime((string) $pivot->last_read_at),
            'Author last_read_at should be >= their own message timestamp (not unread).',
        );
    }

    public function test_sending_does_not_mark_other_users_read(): void
    {
        $this->allowChannelAccess();

        $author = User::factory()->create();
        $other = User::factory()->create();
        $channel = Channel::factory()->create();

        $this->actingAs($author)->postJson(
            route('api.channels.messages.store', $channel),
            ['content' => 'hello world'],
        )->assertCreated();

        // The fix is author-scoped: a different user gets no read marker, so the
        // channel stays unread for them (decorateUnread treats null last_read_at as unread).
        $this->assertNull(
            $this->pivot($channel->id, $other->id),
            'Posting must not create a read marker for other users.',
        );
    }
}
