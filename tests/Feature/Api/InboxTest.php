<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\InboxMessage;
use App\Models\User;
use App\Services\InboxService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->mock(PermissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('userCanViewChannel')->andReturn(true);
            $mock->shouldReceive('userCanInChannel')->andReturn(true);
            $mock->shouldReceive('getUsersWithChannelAccess')->andReturn([]);
        });
    }

    /**
     * @return array{0: User, 1: User, 2: DirectMessageGroup}
     */
    private function seedDmGroup(): array
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $alice->id]);
        $group->participants()->attach([$alice->id, $bob->id]);

        return [$alice, $bob, $group];
    }

    public function test_dm_send_enqueues_inbox_row_for_recipient_not_sender(): void
    {
        [$alice, $bob, $group] = $this->seedDmGroup();

        $this->actingAs($alice)
            ->postJson(route('api.direct-messages.messages.store', $group), [
                'content' => 'hello bob',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('inbox_messages', [
            'user_id' => $bob->id,
            'message_type' => 'direct_message',
        ]);
        $this->assertDatabaseMissing('inbox_messages', [
            'user_id' => $alice->id,
        ]);
        $this->assertSame(1, InboxMessage::query()->count());
    }

    public function test_channel_user_mention_enqueues_inbox_row(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $channel = Channel::factory()->create();

        $this->actingAs($alice)
            ->postJson(route('api.channels.messages.store', $channel), [
                'content' => 'hey @bob',
                'mention_user_ids' => [$bob->id],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('inbox_messages', [
            'user_id' => $bob->id,
            'message_type' => 'channel',
        ]);
        $this->assertDatabaseMissing('inbox_messages', [
            'user_id' => $alice->id,
        ]);
    }

    public function test_everyone_and_here_mentions_do_not_enqueue_inbox(): void
    {
        $alice = User::factory()->create();
        User::factory()->count(2)->create();
        $channel = Channel::factory()->create();

        $this->actingAs($alice)
            ->postJson(route('api.channels.messages.store', $channel), [
                'content' => '@everyone hi',
                'mention_everyone' => true,
            ])
            ->assertCreated();

        $this->actingAs($alice)
            ->postJson(route('api.channels.messages.store', $channel), [
                'content' => '@here hi',
                'mention_here' => true,
            ])
            ->assertCreated();

        $this->assertSame(0, InboxMessage::query()->count());
    }

    public function test_enqueue_is_idempotent(): void
    {
        $bob = User::factory()->create();
        $service = app(InboxService::class);

        $args = [[$bob->id], 'direct_message', 123, ['id' => 123, 'content' => 'hi']];
        $service->enqueueForRecipients(...$args);
        $service->enqueueForRecipients(...$args);

        $this->assertSame(1, InboxMessage::query()->count());
    }

    public function test_inbox_index_returns_pending_for_authenticated_user_only(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $service = app(InboxService::class);

        $service->enqueueForRecipients([$alice->id], 'direct_message', 1, ['id' => 1, 'content' => 'for alice']);
        $service->enqueueForRecipients([$bob->id], 'direct_message', 2, ['id' => 2, 'content' => 'for bob']);

        $response = $this->actingAs($alice)->getJson(route('api.inbox.index'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('direct_message', $data[0]['message_type']);
        $this->assertSame(1, $data[0]['message_id']);
        $this->assertSame('for alice', $data[0]['payload']['content']);
    }

    public function test_ack_deletes_only_specified_rows_and_is_idempotent(): void
    {
        $alice = User::factory()->create();
        $service = app(InboxService::class);
        $service->enqueueForRecipients([$alice->id], 'direct_message', 1, ['id' => 1]);
        $service->enqueueForRecipients([$alice->id], 'channel', 2, ['id' => 2]);

        $ackBody = ['items' => [['message_type' => 'direct_message', 'message_id' => 1]]];

        $first = $this->actingAs($alice)->postJson(route('api.inbox.ack'), $ackBody);
        $first->assertOk()->assertJsonPath('data.deleted', 1);

        $this->assertDatabaseMissing('inbox_messages', [
            'user_id' => $alice->id,
            'message_type' => 'direct_message',
            'message_id' => 1,
        ]);
        $this->assertDatabaseHas('inbox_messages', [
            'user_id' => $alice->id,
            'message_type' => 'channel',
            'message_id' => 2,
        ]);

        // Second ack of the same (already-deleted) item is a no-op, still 200.
        $this->actingAs($alice)->postJson(route('api.inbox.ack'), $ackBody)
            ->assertOk()
            ->assertJsonPath('data.deleted', 0);
    }

    public function test_ack_validation_rejects_bad_message_type(): void
    {
        $alice = User::factory()->create();

        $this->actingAs($alice)
            ->postJson(route('api.inbox.ack'), [
                'items' => [['message_type' => 'bogus', 'message_id' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/items.0.message_type');
    }

    public function test_ack_does_not_delete_other_users_rows(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $service = app(InboxService::class);
        $service->enqueueForRecipients([$alice->id, $bob->id], 'direct_message', 5, ['id' => 5]);

        $this->actingAs($alice)->postJson(route('api.inbox.ack'), [
            'items' => [['message_type' => 'direct_message', 'message_id' => 5]],
        ])->assertOk();

        $this->assertDatabaseMissing('inbox_messages', ['user_id' => $alice->id, 'message_id' => 5]);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $bob->id, 'message_id' => 5]);
    }
}
