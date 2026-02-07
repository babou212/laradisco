<?php

namespace Tests\Feature\Models;

use App\Enums\ChannelType;
use App\Models\Category;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Message;
use App\Models\Role;
use App\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_can_be_created(): void
    {
        $channel = Channel::factory()->create(['name' => 'general']);

        $this->assertDatabaseHas('channels', ['name' => 'general']);
    }

    public function test_channel_type_is_cast_to_enum(): void
    {
        $channel = Channel::factory()->create(['type' => 'text']);

        $this->assertInstanceOf(ChannelType::class, $channel->type);
        $this->assertSame(ChannelType::Text, $channel->type);
    }

    public function test_channel_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $channel = Channel::factory()->for($category)->create();

        $this->assertTrue($channel->category->is($category));
    }

    public function test_category_has_ordered_channels(): void
    {
        $category = Category::factory()->create();
        Channel::factory()->for($category)->create(['position' => 2, 'name' => 'B', 'slug' => 'b']);
        Channel::factory()->for($category)->create(['position' => 0, 'name' => 'A', 'slug' => 'a']);
        Channel::factory()->for($category)->create(['position' => 1, 'name' => 'C', 'slug' => 'c']);

        $channels = $category->channels;

        $this->assertSame('A', $channels->first()->name);
        $this->assertSame('B', $channels->last()->name);
    }

    public function test_channel_has_messages(): void
    {
        $channel = Channel::factory()->create();
        Message::factory()->for($channel)->count(3)->create();

        $this->assertCount(3, $channel->messages);
    }

    public function test_channel_has_threads(): void
    {
        $channel = Channel::factory()->create();
        $message = Message::factory()->for($channel)->create();
        Thread::factory()->for($channel)->create(['message_id' => $message->id]);

        $this->assertCount(1, $channel->threads);
    }

    public function test_channel_has_permission_overrides(): void
    {
        $channel = Channel::factory()->create();
        $role = Role::factory()->create();
        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => $role->id,
        ]);

        $this->assertCount(1, $channel->permissionOverrides);
    }

    public function test_channel_pinned_messages(): void
    {
        $channel = Channel::factory()->create();
        Message::factory()->for($channel)->pinned()->create();
        Message::factory()->for($channel)->create();

        $this->assertCount(1, $channel->pinnedMessages);
    }

    public function test_private_channel_factory_state(): void
    {
        $channel = Channel::factory()->private()->create();

        $this->assertTrue($channel->is_private);
    }
}
