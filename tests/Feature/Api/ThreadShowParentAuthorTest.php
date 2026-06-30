<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ThreadShowParentAuthorTest extends TestCase
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
     * The thread `show` endpoint nests the parent message in `meta.parent_message`.
     * That sub-document must side-load the parent author into its own `included`,
     * otherwise the client renders the thread header author as "Unknown" — and the
     * thread's own `user` (the first replier) is NOT a reliable source for it.
     */
    public function test_parent_message_meta_includes_its_author(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $channel = Channel::factory()->create();
        $parent = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $author->id]);

        // $replier posts the first reply, which creates the thread with user_id = $replier
        // (distinct from the parent author $author).
        $this->actingAs($replier)
            ->postJson(route('api.channels.messages.thread.store', [$channel, $parent]), ['content' => 'first reply'])
            ->assertCreated();

        $thread = $parent->refresh()->threadStarted;
        $this->assertNotNull($thread);
        $this->assertNotSame($author->id, $thread->user_id, 'Thread creator should differ from parent author for a meaningful test.');

        $response = $this->actingAs($replier)
            ->getJson(route('api.channels.threads.show', [$channel, $thread]))
            ->assertOk();

        $parentMessage = $response->json('meta.parent_message');
        $this->assertNotNull($parentMessage, 'meta.parent_message should be present.');

        // The parent's user relationship points at the original author.
        $relUserId = data_get($parentMessage, 'data.relationships.user.data.id');
        $this->assertEquals((string) $author->id, (string) $relUserId);

        // ...and that user must be side-loaded into the sub-document's own `included`.
        $includedUserIds = collect($parentMessage['included'] ?? [])
            ->where('type', 'users')
            ->pluck('id')
            ->map(fn ($id) => (string) $id);

        $this->assertContains((string) $author->id, $includedUserIds->all(),
            'Parent author must be side-loaded into meta.parent_message.included.');
    }
}
