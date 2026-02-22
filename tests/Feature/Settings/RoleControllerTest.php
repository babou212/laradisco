<?php

namespace Tests\Feature\Settings;

use App\Enums\PermissionFlag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(int $position = 100): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->admin()->create(['position' => $position]);
        $user->roles()->attach($role);

        return $user;
    }

    private function createUserWithPermission(PermissionFlag $permission, int $position = 50): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->create([
            'permissions' => [$permission->value],
            'position' => $position,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    // --- Index ---

    public function test_guest_cannot_access_roles_page(): void
    {
        $response = $this->get(route('settings.roles.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_roles_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.roles.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_roles_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('settings.roles.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Roles')
            ->has('roles')
            ->has('permissions')
        );
    }

    public function test_user_with_manage_roles_permission_can_access_roles_page(): void
    {
        $user = $this->createUserWithPermission(PermissionFlag::ManageRoles);

        $response = $this->actingAs($user)->get(route('settings.roles.index'));

        $response->assertOk();
    }

    // --- Store ---

    public function test_admin_can_create_role(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('settings.roles.store'), [
            'name' => 'Moderator',
            'color' => '#ff5733',
            'permissions' => [PermissionFlag::ManageMessages->value],
            'position' => 10,
        ]);

        $response->assertRedirect(route('settings.roles.index'));
        $this->assertDatabaseHas('roles', [
            'name' => 'Moderator',
            'color' => '#ff5733',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('settings.roles.store'), [
            'name' => '',
            'color' => '#ff5733',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_validates_unique_name(): void
    {
        $admin = $this->createAdmin();
        Role::factory()->create(['name' => 'ExistingRole']);

        $response = $this->actingAs($admin)->post(route('settings.roles.store'), [
            'name' => 'ExistingRole',
            'color' => '#ff5733',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_unauthorized_user_cannot_create_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.roles.store'), [
            'name' => 'Hacked Role',
        ]);

        $response->assertForbidden();
    }

    // --- Update ---

    public function test_admin_can_update_role(): void
    {
        $admin = $this->createAdmin();
        $role = Role::factory()->create(['position' => 10, 'name' => 'OldName']);

        $response = $this->actingAs($admin)->put(route('settings.roles.update', $role), [
            'name' => 'NewName',
            'color' => '#00ff00',
            'permissions' => [PermissionFlag::SendMessages->value],
        ]);

        $response->assertRedirect(route('settings.roles.index'));
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'NewName',
        ]);
    }

    public function test_unauthorized_user_cannot_update_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->put(route('settings.roles.update', $role), [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    // --- Destroy ---

    public function test_admin_can_delete_role(): void
    {
        $admin = $this->createAdmin();
        $role = Role::factory()->create(['position' => 10]);

        $response = $this->actingAs($admin)->delete(route('settings.roles.destroy', $role));

        $response->assertRedirect(route('settings.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_default_role(): void
    {
        $admin = $this->createAdmin();
        $defaultRole = Role::factory()->everyone()->create();

        $response = $this->actingAs($admin)->delete(route('settings.roles.destroy', $defaultRole));

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $defaultRole->id]);
    }

    public function test_cannot_delete_role_at_or_above_own_position(): void
    {
        $admin = $this->createAdmin(position: 50);
        $higherRole = Role::factory()->create(['position' => 80]);

        $response = $this->actingAs($admin)->delete(route('settings.roles.destroy', $higherRole));

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $higherRole->id]);
    }

    public function test_unauthorized_user_cannot_delete_role(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->delete(route('settings.roles.destroy', $role));

        $response->assertForbidden();
    }

    public function test_deleting_role_detaches_users(): void
    {
        $admin = $this->createAdmin();
        $role = Role::factory()->create(['position' => 10]);
        $member = User::factory()->create();
        $role->users()->attach($member);

        $this->actingAs($admin)->delete(route('settings.roles.destroy', $role));

        $this->assertDatabaseMissing('role_user', [
            'role_id' => $role->id,
            'user_id' => $member->id,
        ]);
    }
}
