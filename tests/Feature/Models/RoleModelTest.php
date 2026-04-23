<?php

namespace Tests\Feature\Models;

use App\Enums\PermissionFlag;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_role_can_be_created(): void
    {
        $role = Role::factory()->create(['name' => 'TestRole']);

        $this->assertDatabaseHas('roles', ['name' => 'TestRole']);
    }

    public function test_everyone_role_has_default_permissions(): void
    {
        $role = Role::factory()->everyone()->create();
        $role->syncPermissions([
            PermissionFlag::ViewChannels->value,
            PermissionFlag::SendMessages->value,
            PermissionFlag::ReadMessageHistory->value,
        ]);

        $this->assertTrue($role->is_default);
        $this->assertTrue($role->hasPermissionTo(PermissionFlag::ViewChannels->value));
        $this->assertTrue($role->hasPermissionTo(PermissionFlag::SendMessages->value));
        $this->assertFalse($role->hasPermissionTo(PermissionFlag::ManageChannels->value));
    }

    public function test_admin_role_has_all_permissions(): void
    {
        $role = Role::factory()->admin()->create();
        $role->syncPermissions([PermissionFlag::Administrator->value]);

        $this->assertTrue($role->hasPermissionTo(PermissionFlag::Administrator->value));
    }

    public function test_moderator_role_has_moderation_permissions(): void
    {
        $role = Role::factory()->moderator()->create();
        $role->syncPermissions([
            PermissionFlag::ManageMessages->value,
            PermissionFlag::KickMembers->value,
            PermissionFlag::BanMembers->value,
        ]);

        $this->assertTrue($role->hasPermissionTo(PermissionFlag::ManageMessages->value));
        $this->assertTrue($role->hasPermissionTo(PermissionFlag::KickMembers->value));
        $this->assertTrue($role->hasPermissionTo(PermissionFlag::BanMembers->value));
        $this->assertFalse($role->hasPermissionTo(PermissionFlag::Administrator->value));
    }

    public function test_role_can_grant_permission(): void
    {
        $role = Role::factory()->create();

        $role->givePermissionTo(PermissionFlag::SendMessages->value);

        $this->assertTrue($role->fresh()->hasPermissionTo(PermissionFlag::SendMessages->value));
    }

    public function test_role_can_revoke_permission(): void
    {
        $role = Role::factory()->create();
        $role->givePermissionTo(PermissionFlag::SendMessages->value);

        $role->revokePermissionTo(PermissionFlag::SendMessages->value);

        $this->assertFalse($role->fresh()->hasPermissionTo(PermissionFlag::SendMessages->value));
    }

    public function test_granting_duplicate_permission_does_not_add_twice(): void
    {
        $role = Role::factory()->create();
        $role->givePermissionTo(PermissionFlag::SendMessages->value);

        $role->givePermissionTo(PermissionFlag::SendMessages->value);

        $this->assertCount(1, $role->fresh()->permissions);
    }

    public function test_role_has_users_relationship(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertCount(1, $role->users);
    }

    public function test_role_permissions_are_collection(): void
    {
        $role = Role::factory()->create();
        $role->givePermissionTo(PermissionFlag::SendMessages->value);

        $this->assertInstanceOf(Collection::class, $role->permissions);
    }

    public function test_role_name_must_be_unique(): void
    {
        Role::factory()->create(['name' => 'UniqueRole']);

        $this->expectException(QueryException::class);

        Role::factory()->create(['name' => 'UniqueRole']);
    }
}
