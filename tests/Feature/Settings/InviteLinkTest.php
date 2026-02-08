<?php

namespace Tests\Feature\Settings;

use App\Enums\PermissionFlag;
use App\Models\InviteLink;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteLinkTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithInvitePermission(): User
    {
        $role = Role::factory()->create([
            'permissions' => [PermissionFlag::InviteMembers->value],
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function createUserWithoutInvitePermission(): User
    {
        return User::factory()->create();
    }

    public function test_guests_cannot_access_invite_links_page(): void
    {
        $response = $this->get(route('invite-links.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_invite_links(): void
    {
        $response = $this->post(route('invite-links.store'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_delete_invite_links(): void
    {
        $inviteLink = InviteLink::factory()->create();

        $response = $this->delete(route('invite-links.destroy', $inviteLink));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_invite_links_page(): void
    {
        $user = $this->createUserWithoutInvitePermission();

        $response = $this->actingAs($user)->get(route('invite-links.index'));

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_create_invite_links(): void
    {
        $user = $this->createUserWithoutInvitePermission();

        $response = $this->actingAs($user)->post(route('invite-links.store'));

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_delete_invite_links(): void
    {
        $user = $this->createUserWithoutInvitePermission();
        $inviteLink = InviteLink::factory()->create();

        $response = $this->actingAs($user)->delete(route('invite-links.destroy', $inviteLink));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_invite_links_page(): void
    {
        $user = $this->createUserWithInvitePermission();

        $response = $this->actingAs($user)->get(route('invite-links.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/InviteLinks')
            ->has('inviteLinks')
        );
    }

    public function test_invite_links_page_displays_existing_links(): void
    {
        $user = $this->createUserWithInvitePermission();

        InviteLink::factory()->count(3)->create(['created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('invite-links.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/InviteLinks')
            ->has('inviteLinks', 3)
        );
    }

    public function test_authorized_user_can_generate_invite_link(): void
    {
        $user = $this->createUserWithInvitePermission();

        $response = $this->actingAs($user)->post(route('invite-links.store'));

        $response->assertRedirect(route('invite-links.index'));

        $this->assertDatabaseCount('invite_links', 1);

        $inviteLink = InviteLink::first();
        $this->assertSame($user->id, $inviteLink->created_by);
        $this->assertSame(64, strlen($inviteLink->token));
        $this->assertNull($inviteLink->used_at);
        $this->assertNull($inviteLink->used_by);
        $this->assertTrue($inviteLink->expires_at->isFuture());
    }

    public function test_generated_invite_link_expires_in_one_hour(): void
    {
        $user = $this->createUserWithInvitePermission();

        $this->freezeTime();

        $this->actingAs($user)->post(route('invite-links.store'));

        $inviteLink = InviteLink::first();

        $this->assertTrue(
            $inviteLink->expires_at->between(now()->addMinutes(59), now()->addMinutes(61))
        );
    }

    public function test_authorized_user_can_delete_unused_invite_link(): void
    {
        $user = $this->createUserWithInvitePermission();

        $inviteLink = InviteLink::factory()->create(['created_by' => $user->id]);

        $response = $this->actingAs($user)->delete(route('invite-links.destroy', $inviteLink));

        $response->assertRedirect(route('invite-links.index'));

        $this->assertDatabaseMissing('invite_links', ['id' => $inviteLink->id]);
    }

    public function test_authorized_user_cannot_delete_used_invite_link(): void
    {
        $user = $this->createUserWithInvitePermission();

        $inviteLink = InviteLink::factory()->used()->create(['created_by' => $user->id]);

        $response = $this->actingAs($user)->delete(route('invite-links.destroy', $inviteLink));

        $response->assertForbidden();

        $this->assertDatabaseHas('invite_links', ['id' => $inviteLink->id]);
    }

    public function test_each_generated_link_has_unique_token(): void
    {
        $user = $this->createUserWithInvitePermission();

        $this->actingAs($user)->post(route('invite-links.store'));
        $this->actingAs($user)->post(route('invite-links.store'));

        $tokens = InviteLink::pluck('token');

        $this->assertCount(2, $tokens);
        $this->assertCount(2, $tokens->unique());
    }

    public function test_admin_user_can_manage_invite_links(): void
    {
        $adminRole = Role::factory()->create([
            'permissions' => [PermissionFlag::Administrator->value],
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $response = $this->actingAs($admin)->get(route('invite-links.index'));
        $response->assertOk();

        $response = $this->actingAs($admin)->post(route('invite-links.store'));
        $response->assertRedirect(route('invite-links.index'));

        $inviteLink = InviteLink::first();

        $response = $this->actingAs($admin)->delete(route('invite-links.destroy', $inviteLink));
        $response->assertRedirect(route('invite-links.index'));
    }
}
