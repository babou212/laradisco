<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(int $position = 100): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->admin()->create(['position' => $position]);
        $user->roles()->attach($role);

        return $user;
    }

    private function createMemberWithRole(int $position = 10): array
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['position' => $position]);
        $user->roles()->attach($role);

        return [$user, $role];
    }

    // --- Index ---

    public function test_guest_cannot_access_members_page(): void
    {
        $response = $this->get(route('settings.members.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_members_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.members.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_members_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('settings.members.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Members')
            ->has('members')
            ->has('roles')
        );
    }

    // --- Assign Role ---

    public function test_admin_can_assign_role_to_user(): void
    {
        $admin = $this->createAdmin();
        $member = User::factory()->create();
        $role = Role::factory()->create(['position' => 10]);

        $response = $this->actingAs($admin)->post(
            route('settings.members.assign-role', $member),
            ['role_id' => $role->id]
        );

        $response->assertRedirect(route('settings.members.index'));
        $this->assertDatabaseHas('role_user', [
            'user_id' => $member->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_cannot_assign_role_above_own_position(): void
    {
        $admin = $this->createAdmin(position: 50);
        $member = User::factory()->create();
        $higherRole = Role::factory()->create(['position' => 80]);

        $response = $this->actingAs($admin)->post(
            route('settings.members.assign-role', $member),
            ['role_id' => $higherRole->id]
        );

        $response->assertForbidden();
    }

    public function test_cannot_assign_role_to_user_with_higher_position(): void
    {
        $admin = $this->createAdmin(position: 50);
        [$higherMember] = $this->createMemberWithRole(position: 80);
        $lowRole = Role::factory()->create(['position' => 5]);

        $response = $this->actingAs($admin)->post(
            route('settings.members.assign-role', $higherMember),
            ['role_id' => $lowRole->id]
        );

        $response->assertForbidden();
    }

    public function test_assign_role_validates_role_id(): void
    {
        $admin = $this->createAdmin();
        $member = User::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('settings.members.assign-role', $member),
            ['role_id' => 99999]
        );

        $response->assertSessionHasErrors('role_id');
    }

    public function test_unauthorized_user_cannot_assign_roles(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->post(
            route('settings.members.assign-role', $member),
            ['role_id' => $role->id]
        );

        $response->assertForbidden();
    }

    // --- Remove Role ---

    public function test_admin_can_remove_role_from_user(): void
    {
        $admin = $this->createAdmin();
        $role = Role::factory()->create(['position' => 10]);
        $member = User::factory()->create();
        $member->roles()->attach($role);

        $response = $this->actingAs($admin)->delete(
            route('settings.members.remove-role', $member),
            ['role_id' => $role->id]
        );

        $response->assertRedirect(route('settings.members.index'));
        $this->assertDatabaseMissing('role_user', [
            'user_id' => $member->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_cannot_remove_default_role(): void
    {
        $admin = $this->createAdmin();
        $defaultRole = Role::factory()->everyone()->create();
        $member = User::factory()->create();
        $member->roles()->attach($defaultRole);

        $response = $this->actingAs($admin)->delete(
            route('settings.members.remove-role', $member),
            ['role_id' => $defaultRole->id]
        );

        $response->assertForbidden();
    }

    public function test_cannot_remove_role_above_own_position(): void
    {
        $admin = $this->createAdmin(position: 50);
        $higherRole = Role::factory()->create(['position' => 80]);
        $member = User::factory()->create();
        $member->roles()->attach($higherRole);

        $response = $this->actingAs($admin)->delete(
            route('settings.members.remove-role', $member),
            ['role_id' => $higherRole->id]
        );

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_remove_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $member = User::factory()->create();
        $member->roles()->attach($role);

        $response = $this->actingAs($user)->delete(
            route('settings.members.remove-role', $member),
            ['role_id' => $role->id]
        );

        $response->assertForbidden();
    }

    public function test_members_page_returns_unique_roles_per_member(): void
    {
        $admin = $this->createAdmin();
        $member = User::factory()->create();
        $role = Role::factory()->create(['position' => 10]);

        // Attach via syncWithoutDetaching to verify idempotent assignment
        $member->roles()->syncWithoutDetaching([$role->id]);
        $member->roles()->syncWithoutDetaching([$role->id]);

        $response = $this->actingAs($admin)->get(route('settings.members.index'));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($member, $role) {
            $page->component('settings/Members')
                ->has('members');

            $members = $page->toArray()['props']['members'];
            $targetMember = collect($members)->firstWhere('id', $member->id);
            $this->assertNotNull($targetMember);
            $roleIds = collect($targetMember['roles'])->pluck('id')->toArray();
            $this->assertCount(1, $roleIds, 'Role should appear only once');
            $this->assertEquals($role->id, $roleIds[0]);
        });
    }
}
