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
            'data' => [
                'id',
                'type',
                'attributes' => ['username'],
                'relationships' => [
                    'roles' => [
                        'data' => [
                            '*' => ['id', 'type'],
                        ],
                    ],
                ],
            ],
            'included' => [
                '*' => [
                    'id',
                    'type',
                    'attributes' => ['name', 'color', 'position'],
                ],
            ],
        ]);

        $response->assertJsonCount(2, 'data.relationships.roles.data');
    }

    public function test_user_api_returns_roles_without_duplicates(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $role = Role::factory()->create();
        // Attach the role and verify it only appears once in the response
        $user->roles()->attach($role->id);

        $response = $this->actingAs($viewer)->getJson(route('api.users.show', $user));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.relationships.roles.data');
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
        $included = $response->json('included');
        $this->assertEquals('High', $included[0]['attributes']['name']);
        $this->assertEquals('Low', $included[1]['attributes']['name']);
    }

    public function test_user_api_does_not_expose_sensitive_fields(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($viewer)->getJson(route('api.users.show', $user));

        $response->assertOk();
        $attributes = $response->json('data.attributes');
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('two_factor_secret', $attributes);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
    }
}
