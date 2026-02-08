<?php

namespace Tests\Feature\Auth;

use App\Models\InviteLink;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_requires_valid_invite_token(): void
    {
        $response = $this->get(route('register'));

        $response->assertForbidden();
    }

    public function test_registration_screen_can_be_rendered_with_valid_invite(): void
    {
        $invite = InviteLink::factory()->create();

        $response = $this->get(route('register', ['invite' => $invite->token]));

        $response->assertOk();
    }

    public function test_registration_screen_rejects_expired_invite(): void
    {
        $invite = InviteLink::factory()->expired()->create();

        $response = $this->get(route('register', ['invite' => $invite->token]));

        $response->assertForbidden();
    }

    public function test_registration_screen_rejects_used_invite(): void
    {
        $invite = InviteLink::factory()->used()->create();

        $response = $this->get(route('register', ['invite' => $invite->token]));

        $response->assertForbidden();
    }

    public function test_new_users_can_register_with_valid_invite(): void
    {
        Role::factory()->everyone()->create();
        $invite = InviteLink::factory()->create();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_consumes_invite_link(): void
    {
        Role::factory()->everyone()->create();
        $invite = InviteLink::factory()->create();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $invite->refresh();
        $this->assertNotNull($invite->used_at);
        $this->assertNotNull($invite->used_by);
    }

    public function test_registration_assigns_default_role(): void
    {
        $everyoneRole = Role::factory()->everyone()->create();
        $invite = InviteLink::factory()->create();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->roles->contains($everyoneRole));
    }

    public function test_registration_fails_without_invite_token(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('invite');
        $this->assertGuest();
    }

    public function test_registration_fails_with_expired_invite(): void
    {
        $invite = InviteLink::factory()->expired()->create();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $response->assertSessionHasErrors('invite');
        $this->assertGuest();
    }

    public function test_registration_fails_with_already_used_invite(): void
    {
        $invite = InviteLink::factory()->used()->create();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $response->assertSessionHasErrors('invite');
        $this->assertGuest();
    }

    public function test_invite_link_cannot_be_reused(): void
    {
        Role::factory()->everyone()->create();
        $invite = InviteLink::factory()->create();

        // First registration succeeds...
        $this->post(route('register.store'), [
            'name' => 'User One',
            'username' => 'userone',
            'email' => 'one@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        // Log out...
        $this->post(route('logout'));

        // Second registration fails...
        $response = $this->post(route('register.store'), [
            'name' => 'User Two',
            'username' => 'usertwo',
            'email' => 'two@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $response->assertSessionHasErrors('invite');
    }
}
