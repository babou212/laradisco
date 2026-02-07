<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageActionsTest extends TestCase
{
    use RefreshDatabase;

    private function createChannelWithMessage(): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'Original message',
        ]);

        return [$user, $channel, $message];
    }

    // --- Edit tests ---

    public function test_user_can_edit_their_own_message(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        $response = $this->actingAs($user)
            ->putJson(route('channels.messages.update', [$channel, $message]), [
                'content' => 'Updated message',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Updated message');

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'Updated message',
            'is_edited' => true,
        ]);
    }

    public function test_user_cannot_edit_another_users_message(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->putJson(route('channels.messages.update', [$channel, $message]), [
                'content' => 'Hacked message',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'Original message',
        ]);
    }

    public function test_edit_requires_content(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        $response = $this->actingAs($user)
            ->putJson(route('channels.messages.update', [$channel, $message]), [
                'content' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    public function test_edit_enforces_max_length(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        $response = $this->actingAs($user)
            ->putJson(route('channels.messages.update', [$channel, $message]), [
                'content' => str_repeat('a', 2001),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    // --- Delete tests ---

    public function test_user_can_delete_their_own_message(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        $response = $this->actingAs($user)
            ->deleteJson(route('channels.messages.destroy', [$channel, $message]));

        $response->assertOk();
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_user_cannot_delete_another_users_message(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->deleteJson(route('channels.messages.destroy', [$channel, $message]));

        $response->assertForbidden();
        $this->assertNotSoftDeleted('messages', ['id' => $message->id]);
    }

    // --- Reaction tests ---

    public function test_user_can_add_reaction(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        $response = $this->actingAs($user)
            ->postJson(route('channels.messages.reactions.toggle', [$channel, $message]), [
                'emoji' => '👍',
            ]);

        $response->assertOk();
        $response->assertJsonPath('added', true);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_user_can_remove_reaction(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        MessageReaction::factory()->create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('channels.messages.reactions.toggle', [$channel, $message]), [
                'emoji' => '👍',
            ]);

        $response->assertOk();
        $response->assertJsonPath('added', false);

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_reaction_requires_emoji(): void
    {
        [$user, $channel, $message] = $this->createChannelWithMessage();

        $response = $this->actingAs($user)
            ->postJson(route('channels.messages.reactions.toggle', [$channel, $message]), [
                'emoji' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('emoji');
    }

    // --- Typing indicator tests ---

    public function test_user_can_send_typing_indicator(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->postJson(route('channels.typing', $channel));

        $response->assertOk();
    }

    public function test_guest_cannot_access_message_actions(): void
    {
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);
        $message = Message::factory()->create(['channel_id' => $channel->id]);

        $this->putJson(route('channels.messages.update', [$channel, $message]), [
            'content' => 'test',
        ])->assertUnauthorized();

        $this->deleteJson(route('channels.messages.destroy', [$channel, $message]))
            ->assertUnauthorized();

        $this->postJson(route('channels.messages.reactions.toggle', [$channel, $message]), [
            'emoji' => '👍',
        ])->assertUnauthorized();

        $this->postJson(route('channels.typing', $channel))
            ->assertUnauthorized();
    }
}
