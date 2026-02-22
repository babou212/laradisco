<?php

namespace Tests\Feature\Settings;

use App\Enums\PermissionFlag;
use App\Models\Category;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->admin()->create(['position' => 100]);
        $user->roles()->attach($role);

        return $user;
    }

    private function createRegularUser(): User
    {
        return User::factory()->create();
    }

    // --- Index ---

    public function test_guest_cannot_access_channels_page(): void
    {
        $response = $this->get(route('settings.channels.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_channels_page(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get(route('settings.channels.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_channels_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('settings.channels.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Channels')
            ->has('categories')
            ->has('roles')
            ->has('permissions')
        );
    }

    // --- Store Channel ---

    public function test_admin_can_create_channel(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('settings.channels.store'), [
            'name' => 'general',
            'type' => 'text',
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('channels', [
            'name' => 'general',
            'category_id' => $category->id,
        ]);
    }

    public function test_store_channel_validates_name(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('settings.channels.store'), [
            'name' => '',
            'type' => 'text',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_channel_validates_type(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('settings.channels.store'), [
            'name' => 'test',
            'type' => 'invalid',
            'category_id' => $category->id,
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_store_channel_rejects_announcement_type(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('settings.channels.store'), [
            'name' => 'announcements',
            'type' => 'announcement',
            'category_id' => $category->id,
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_store_channel_accepts_text_type(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('settings.channels.store'), [
            'name' => 'text-channel',
            'type' => 'text',
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('channels', ['name' => 'text-channel', 'type' => 'text']);
    }

    public function test_store_channel_accepts_voice_type(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('settings.channels.store'), [
            'name' => 'voice-channel',
            'type' => 'voice',
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('channels', ['name' => 'voice-channel', 'type' => 'voice']);
    }

    public function test_unauthorized_user_cannot_create_channel(): void
    {
        $user = $this->createRegularUser();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.channels.store'), [
            'name' => 'hacked-channel',
            'type' => 'text',
            'category_id' => $category->id,
        ]);

        $response->assertForbidden();
    }

    // --- Update Channel ---

    public function test_admin_can_update_channel(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->put(route('settings.channels.update', $channel), [
            'name' => 'renamed-channel',
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'name' => 'renamed-channel',
        ]);
    }

    // --- Destroy Channel ---

    public function test_admin_can_delete_channel(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->delete(route('settings.channels.destroy', $channel));

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    public function test_unauthorized_user_cannot_delete_channel(): void
    {
        $user = $this->createRegularUser();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->delete(route('settings.channels.destroy', $channel));

        $response->assertForbidden();
    }

    // --- Store Category ---

    public function test_admin_can_create_category(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('settings.categories.store'), [
            'name' => 'New Category',
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('categories', ['name' => 'New Category']);
    }

    public function test_store_category_validates_name(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('settings.categories.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    // --- Update Category ---

    public function test_admin_can_update_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->put(route('settings.categories.update', $category), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    // --- Destroy Category ---

    public function test_admin_can_delete_category_and_its_channels(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->delete(route('settings.categories.destroy', $category));

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    // --- Channel Permission Overrides ---

    public function test_admin_can_get_channel_overrides(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);
        $role = Role::factory()->create();

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => $role->id,
            'allow' => [PermissionFlag::SendMessages->value],
            'deny' => [],
        ]);

        $response = $this->actingAs($admin)->get(route('settings.channels.overrides.index', $channel));

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_admin_can_store_channel_override(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);
        $role = Role::factory()->create();

        $response = $this->actingAs($admin)->post(route('settings.channels.overrides.store', $channel), [
            'role_id' => $role->id,
            'allow' => [PermissionFlag::SendMessages->value],
            'deny' => [PermissionFlag::ManageMessages->value],
        ]);

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseHas('channel_permission_overrides', [
            'channel_id' => $channel->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_store_override_requires_role_or_user(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->post(route('settings.channels.overrides.store', $channel), [
            'allow' => [PermissionFlag::SendMessages->value],
            'deny' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_delete_channel_override(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);
        $override = ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
        ]);

        $response = $this->actingAs($admin)->delete(
            route('settings.channels.overrides.destroy', [$channel, $override])
        );

        $response->assertRedirect(route('settings.channels.index'));
        $this->assertDatabaseMissing('channel_permission_overrides', ['id' => $override->id]);
    }

    public function test_unauthorized_user_cannot_manage_overrides(): void
    {
        $user = $this->createRegularUser();
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)->get(route('settings.channels.overrides.index', $channel));

        $response->assertForbidden();
    }
}
