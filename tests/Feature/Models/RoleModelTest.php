<?php

namespace Tests\Feature\Models;

use App\Enums\PermissionFlag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_can_be_created(): void
    {
        $role = Role::factory()->create(['name' => 'TestRole']);

        $this->assertDatabaseHas('roles', ['name' => 'TestRole']);
    }

    public function test_everyone_role_has_default_permissions(): void
    {
        $role = Role::factory()->everyone()->create();

        $this->assertTrue($role->is_default);
        $this->assertTrue($role->hasPermission(PermissionFlag::ViewChannels));
        $this->assertTrue($role->hasPermission(PermissionFlag::SendMessages));
        $this->assertFalse($role->hasPermission(PermissionFlag::ManageChannels));
    }

    public function test_admin_role_has_all_permissions(): void
    {
        $role = Role::factory()->admin()->create();

        $this->assertTrue($role->hasPermission(PermissionFlag::Administrator));
        $this->assertTrue($role->hasPermission(PermissionFlag::ManageChannels));
        $this->assertTrue($role->hasPermission(PermissionFlag::BanMembers));
    }

    public function test_moderator_role_has_moderation_permissions(): void
    {
        $role = Role::factory()->moderator()->create();

        $this->assertTrue($role->hasPermission(PermissionFlag::ManageMessages));
        $this->assertTrue($role->hasPermission(PermissionFlag::KickMembers));
        $this->assertTrue($role->hasPermission(PermissionFlag::BanMembers));
        $this->assertFalse($role->hasPermission(PermissionFlag::Administrator));
    }

    public function test_role_can_grant_permission(): void
    {
        $role = Role::factory()->create(['permissions' => []]);

        $role->grantPermission(PermissionFlag::SendMessages);

        $this->assertTrue($role->fresh()->hasPermission(PermissionFlag::SendMessages));
    }

    public function test_role_can_revoke_permission(): void
    {
        $role = Role::factory()->create([
            'permissions' => [PermissionFlag::SendMessages->value],
        ]);

        $role->revokePermission(PermissionFlag::SendMessages);

        $this->assertFalse($role->fresh()->hasPermission(PermissionFlag::SendMessages));
    }

    public function test_granting_duplicate_permission_does_not_add_twice(): void
    {
        $role = Role::factory()->create([
            'permissions' => [PermissionFlag::SendMessages->value],
        ]);

        $role->grantPermission(PermissionFlag::SendMessages);

        $this->assertCount(1, $role->fresh()->permissions);
    }

    public function test_role_has_users_relationship(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        $role->users()->attach($user);

        $this->assertCount(1, $role->users);
    }

    public function test_permissions_cast_to_array(): void
    {
        $role = Role::factory()->create([
            'permissions' => [PermissionFlag::SendMessages->value],
        ]);

        $this->assertIsArray($role->permissions);
    }

    public function test_role_name_must_be_unique(): void
    {
        Role::factory()->create(['name' => 'UniqueRole']);

        $this->expectException(QueryException::class);

        Role::factory()->create(['name' => 'UniqueRole']);
    }
}
