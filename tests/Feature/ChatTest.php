<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $response = $this->get(route('chat'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_chat()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->get(route('chat'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Chat')
            ->has('categories')
            ->has('directMessages')
        );
    }

    public function test_users_can_send_messages_to_channels()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hello, world!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('messages', [
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'Hello, world!',
        ]);
    }

    public function test_message_content_is_required()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->post(route('channels.messages.store', $channel), [
                'content' => '',
            ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_users_can_fetch_channel_messages()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->get(route('channels.show', $channel));

        $response->assertOk();
        $response->assertJsonStructure([
            'channel',
            'messages',
        ]);
    }
}
