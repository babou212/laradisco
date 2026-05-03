<?php

namespace Tests\Feature\Models;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Tests\TestCase;

class MessageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_can_be_created(): void
    {
        $message = Message::factory()->create();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_message_belongs_to_channel(): void
    {
        $channel = Channel::factory()->create();
        $message = Message::factory()->for($channel)->create();

        $this->assertTrue($message->channel->is($channel));
    }

    public function test_message_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->for($user)->create();

        $this->assertTrue($message->user->is($user));
    }

    public function test_message_can_be_a_reply(): void
    {
        $channel = Channel::factory()->create();
        $original = Message::factory()->for($channel)->create();
        $reply = Message::factory()->for($channel)->create(['reply_to_id' => $original->id]);

        $this->assertTrue($reply->replyTo->is($original));
    }

    public function test_message_has_attachments(): void
    {
        $this->fakeS3();

        $message = Message::factory()->create();
        $message->addMedia(File::image('photo.png', 16, 16)->getPathname())
            ->toMediaCollection('attachments');

        $this->assertCount(1, $message->attachments);
        $this->assertSame('attachments', $message->attachments->first()->collection_name);
    }

    public function test_message_has_reactions(): void
    {
        $message = Message::factory()->create();
        MessageReaction::factory()->for($message)->create();

        $this->assertCount(1, $message->reactions);
    }

    public function test_message_can_be_soft_deleted(): void
    {
        $message = Message::factory()->create();

        $message->delete();

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_message_edited_factory_state(): void
    {
        $message = Message::factory()->edited()->create();

        $this->assertTrue($message->is_edited);
        $this->assertNotNull($message->edited_at);
    }

    public function test_message_pinned_factory_state(): void
    {
        $message = Message::factory()->pinned()->create();

        $this->assertTrue($message->is_pinned);
    }

    public function test_message_can_belong_to_thread(): void
    {
        $channel = Channel::factory()->create();
        $parentMessage = Message::factory()->for($channel)->create();
        $thread = Thread::factory()->create([
            'channel_id' => $channel->id,
            'message_id' => $parentMessage->id,
        ]);
        $threadMessage = Message::factory()->for($channel)->create([
            'thread_id' => $thread->id,
        ]);

        $this->assertTrue($threadMessage->thread->is($thread));
    }
}
