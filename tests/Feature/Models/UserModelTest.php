<?php

namespace Tests\Feature\Models;

use App\Enums\PermissionFlag;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_factory(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_user_has_username(): void
    {
        $user = User::factory()->create(['username' => 'johndoe']);

        $this->assertSame('johndoe', $user->username);
    }

    public function test_user_online_state_updates_last_seen(): void
    {
        $user = User::factory()->online()->create();

        $this->assertNotNull($user->last_seen_at);
        $this->assertTrue($user->last_seen_at->isToday());
    }

    public function test_user_display_name_returns_nickname_when_set(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'nickname' => 'JD',
        ]);

        $this->assertSame('JD', $user->display_name);
    }

    public function test_user_display_name_returns_name_when_no_nickname(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'nickname' => null,
        ]);

        $this->assertSame('John Doe', $user->display_name);
    }

    public function test_user_can_have_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $user->roles()->attach($role);

        $this->assertTrue($user->roles->contains($role));
    }

    public function test_user_has_permission_through_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create([
            'permissions' => [PermissionFlag::SendMessages->value],
        ]);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission(PermissionFlag::SendMessages));
        $this->assertFalse($user->hasPermission(PermissionFlag::ManageChannels));
    }

    public function test_admin_user_has_all_permissions(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::factory()->admin()->create();
        $user->roles()->attach($adminRole);

        $this->assertTrue($user->isAdministrator());
        $this->assertTrue($user->hasPermission(PermissionFlag::ManageChannels));
        $this->assertTrue($user->hasPermission(PermissionFlag::BanMembers));
    }

    public function test_user_has_messages_relationship(): void
    {
        $user = User::factory()->create();
        Message::factory()->for($user)->create();

        $this->assertCount(1, $user->messages);
    }

    public function test_user_has_reactions_relationship(): void
    {
        $user = User::factory()->create();
        MessageReaction::factory()->for($user)->create();

        $this->assertCount(1, $user->reactions);
    }

    public function test_user_has_direct_message_groups_relationship(): void
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create();
        $group->participants()->attach($user);

        $this->assertCount(1, $user->directMessageGroups);
    }

    public function test_user_with_profile_factory_state(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->assertNotNull($user->nickname);
        $this->assertNotNull($user->about_me);
        $this->assertNotNull($user->custom_status);
    }
}
