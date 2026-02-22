<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_user_api(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(route('api.users.show', $user));

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_user_with_roles(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $adminRole = Role::factory()->admin()->create();
        $modRole = Role::factory()->moderator()->create();
        $user->roles()->attach([$adminRole->id, $modRole->id]);

        $response = $this->actingAs($viewer)->getJson(route('api.users.show', $user));

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'username',
            'roles' => [
                '*' => ['id', 'name', 'color', 'position'],
            ],
        ]);

        $response->assertJsonCount(2, 'roles');
    }

    public function test_user_api_returns_roles_without_duplicates(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $role = Role::factory()->create();
        // Deliberately attach the same role twice to simulate duplicate pivot entries
        $user->roles()->attach($role->id);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($viewer)->getJson(route('api.users.show', $user));

        $response->assertOk();
        $response->assertJsonCount(1, 'roles');
    }

    public function test_user_api_returns_roles_ordered_by_position(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $lowRole = Role::factory()->create(['position' => 10, 'name' => 'Low']);
        $highRole = Role::factory()->create(['position' => 50, 'name' => 'High']);
        $user->roles()->attach([$lowRole->id, $highRole->id]);

        $response = $this->actingAs($viewer)->getJson(route('api.users.show', $user));

        $response->assertOk();
        $roles = $response->json('roles');
        $this->assertEquals('High', $roles[0]['name']);
        $this->assertEquals('Low', $roles[1]['name']);
    }

    public function test_user_api_does_not_expose_sensitive_fields(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($viewer)->getJson(route('api.users.show', $user));

        $response->assertOk();
        $response->assertJsonMissing(['password']);
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertArrayNotHasKey('two_factor_secret', $response->json());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $response->json());
        $this->assertArrayNotHasKey('remember_token', $response->json());
    }
}
