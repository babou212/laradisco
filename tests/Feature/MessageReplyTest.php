<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessageReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reply_to_message(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $originalMessage = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'Original message',
        ]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'This is a reply',
            'reply_to_id' => $originalMessage->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'This is a reply',
            'reply_to_id' => $originalMessage->id,
        ]);
    }

    public function test_reply_loads_with_original_message(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $originalMessage = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'Original message',
        ]);

        $reply = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'This is a reply',
            'reply_to_id' => $originalMessage->id,
        ]);

        $response = $this->actingAs($user)->get("/?channel={$channel->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->has('messages.data')
            ->where('messages.data', fn ($messages) => collect($messages)->contains(fn ($msg) => $msg['id'] === $reply->id &&
                    $msg['reply_to_id'] === $originalMessage->id &&
                    $msg['reply_to']['content'] === $originalMessage->content
            )
            )
        );
    }

    public function test_reply_to_id_must_exist(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'This is a reply',
            'reply_to_id' => 99999,
        ]);

        $response->assertSessionHasErrors('reply_to_id');
    }

    public function test_reply_to_id_is_optional(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'This is a regular message',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'This is a regular message',
            'reply_to_id' => null,
        ]);
    }

    public function test_deleting_original_message_keeps_reply_reference(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $originalMessage = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'Original message',
        ]);

        $reply = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'This is a reply',
            'reply_to_id' => $originalMessage->id,
        ]);

        $originalMessage->delete();
        $reply->refresh();

        // Reply should keep the reference but not be able to load the deleted message
        $this->assertEquals($originalMessage->id, $reply->reply_to_id);
        $this->assertNull($reply->replyTo);
    }
}
