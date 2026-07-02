<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionFlag;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function manager(): User
    {
        $role = Role::factory()->create(['permissions' => [PermissionFlag::ManageChannels->value]]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_reorder_channels_within_a_category_persists_position(): void
    {
        $category = Category::factory()->create();
        $a = Channel::factory()->create(['category_id' => $category->id, 'position' => 0]);
        $b = Channel::factory()->create(['category_id' => $category->id, 'position' => 1]);
        $c = Channel::factory()->create(['category_id' => $category->id, 'position' => 2]);

        $this->actingAs($this->manager())
            ->putJson(route('api.settings.channels.reorder'), [
                'categories' => [
                    ['id' => $category->id, 'channel_ids' => [$c->id, $a->id, $b->id]],
                ],
            ])
            ->assertNoContent();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_reorder_moves_channel_across_categories(): void
    {
        $from = Category::factory()->create();
        $to = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $from->id, 'position' => 0]);
        $existing = Channel::factory()->create(['category_id' => $to->id, 'position' => 0]);

        $this->actingAs($this->manager())
            ->putJson(route('api.settings.channels.reorder'), [
                'categories' => [
                    ['id' => $from->id, 'channel_ids' => []],
                    ['id' => $to->id, 'channel_ids' => [$existing->id, $channel->id]],
                ],
            ])
            ->assertNoContent();

        $moved = $channel->fresh();
        $this->assertSame($to->id, $moved->category_id);
        $this->assertSame(1, $moved->position);
    }

    public function test_reorder_channels_requires_manage_channels_permission(): void
    {
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->putJson(route('api.settings.channels.reorder'), [
                'categories' => [
                    ['id' => $category->id, 'channel_ids' => [$channel->id]],
                ],
            ])
            ->assertForbidden();
    }

    public function test_reorder_channels_validates_ids(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->manager())
            ->putJson(route('api.settings.channels.reorder'), [
                'categories' => [
                    ['id' => $category->id, 'channel_ids' => [999999]],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_reorder_returns_422_on_slug_collision_across_categories(): void
    {
        $from = Category::factory()->create();
        $to = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $from->id, 'name' => 'general', 'slug' => 'general']);
        Channel::factory()->create(['category_id' => $to->id, 'name' => 'general', 'slug' => 'general']);

        $this->actingAs($this->manager())
            ->putJson(route('api.settings.channels.reorder'), [
                'categories' => [
                    ['id' => $to->id, 'channel_ids' => [$channel->id]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/channel_ids');

        // The move was rolled back.
        $this->assertSame($from->id, $channel->fresh()->category_id);
    }

    public function test_reorder_categories_persists_position(): void
    {
        $a = Category::factory()->create(['position' => 0]);
        $b = Category::factory()->create(['position' => 1]);
        $c = Category::factory()->create(['position' => 2]);

        $this->actingAs($this->manager())
            ->putJson(route('api.settings.categories.reorder'), [
                'ids' => [$c->id, $a->id, $b->id],
            ])
            ->assertNoContent();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_reorder_categories_requires_manage_channels_permission(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->putJson(route('api.settings.categories.reorder'), [
                'ids' => [$category->id],
            ])
            ->assertForbidden();
    }
}
