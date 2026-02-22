<?php

namespace Tests\Feature;

use App\Enums\PermissionFlag;
use App\Models\Category;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PermissionService::class);
    }

    private function createUserWithRole(array $permissions = [], int $position = 0): array
    {
        $role = Role::factory()->create([
            'permissions' => array_map(fn (PermissionFlag $p) => $p->value, $permissions),
            'position' => $position,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return [$user, $role];
    }

    private function createChannel(bool $isPrivate = false): Channel
    {
        $category = Category::factory()->create();

        return Channel::factory()->create([
            'category_id' => $category->id,
            'is_private' => $isPrivate,
        ]);
    }

    // --- resolveChannelPermissions ---

    public function test_resolve_base_permissions_from_user_roles(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::SendMessages, PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        $this->assertContains(PermissionFlag::SendMessages->value, $permissions);
        $this->assertContains(PermissionFlag::ViewChannels->value, $permissions);
        $this->assertNotContains(PermissionFlag::ManageChannels->value, $permissions);
    }

    public function test_administrator_gets_all_permissions(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::Administrator]);
        $channel = $this->createChannel();

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        foreach (PermissionFlag::cases() as $flag) {
            $this->assertContains($flag->value, $permissions);
        }
    }

    public function test_role_override_denies_permission(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::SendMessages, PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => $role->id,
            'user_id' => null,
            'allow' => [],
            'deny' => [PermissionFlag::SendMessages->value],
        ]);

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        $this->assertNotContains(PermissionFlag::SendMessages->value, $permissions);
        $this->assertContains(PermissionFlag::ViewChannels->value, $permissions);
    }

    public function test_role_override_allows_new_permission(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => $role->id,
            'user_id' => null,
            'allow' => [PermissionFlag::ManageMessages->value],
            'deny' => [],
        ]);

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        $this->assertContains(PermissionFlag::ManageMessages->value, $permissions);
    }

    public function test_user_override_takes_precedence_over_role_override(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::SendMessages, PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        // Role override denies SendMessages
        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => $role->id,
            'user_id' => null,
            'allow' => [],
            'deny' => [PermissionFlag::SendMessages->value],
        ]);

        // User override allows SendMessages back
        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => null,
            'user_id' => $user->id,
            'allow' => [PermissionFlag::SendMessages->value],
            'deny' => [],
        ]);

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        $this->assertContains(PermissionFlag::SendMessages->value, $permissions);
    }

    public function test_user_override_can_deny_permission(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::SendMessages, PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => null,
            'user_id' => $user->id,
            'allow' => [],
            'deny' => [PermissionFlag::SendMessages->value],
        ]);

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        $this->assertNotContains(PermissionFlag::SendMessages->value, $permissions);
    }

    public function test_multiple_roles_merge_permissions(): void
    {
        $user = User::factory()->create();
        $role1 = Role::factory()->create([
            'permissions' => [PermissionFlag::SendMessages->value],
        ]);
        $role2 = Role::factory()->create([
            'permissions' => [PermissionFlag::ManageMessages->value],
        ]);
        $user->roles()->attach([$role1->id, $role2->id]);
        $channel = $this->createChannel();

        $permissions = $this->service->resolveChannelPermissions($user, $channel);

        $this->assertContains(PermissionFlag::SendMessages->value, $permissions);
        $this->assertContains(PermissionFlag::ManageMessages->value, $permissions);
    }

    // --- userCanInChannel ---

    public function test_user_can_in_channel_returns_true_when_permitted(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::SendMessages, PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        $this->assertTrue($this->service->userCanInChannel($user, $channel, PermissionFlag::SendMessages));
    }

    public function test_user_can_in_channel_returns_false_when_not_permitted(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $channel = $this->createChannel();

        $this->assertFalse($this->service->userCanInChannel($user, $channel, PermissionFlag::ManageMessages));
    }

    public function test_anyone_can_view_public_channel_with_view_permission(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $channel = $this->createChannel(isPrivate: false);

        $this->assertTrue($this->service->userCanViewChannel($user, $channel));
    }

    public function test_private_channel_requires_explicit_override(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::ViewChannels, PermissionFlag::SendMessages]);
        $channel = $this->createChannel(isPrivate: true);

        $this->assertFalse($this->service->userCanViewChannel($user, $channel));
    }

    public function test_private_channel_accessible_with_role_override(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $channel = $this->createChannel(isPrivate: true);

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => $role->id,
            'user_id' => null,
            'allow' => [PermissionFlag::ViewChannels->value],
            'deny' => [],
        ]);

        $this->assertTrue($this->service->userCanViewChannel($user, $channel));
    }

    public function test_private_channel_accessible_with_user_override(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $channel = $this->createChannel(isPrivate: true);

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $channel->id,
            'role_id' => null,
            'user_id' => $user->id,
            'allow' => [PermissionFlag::ViewChannels->value],
            'deny' => [],
        ]);

        $this->assertTrue($this->service->userCanViewChannel($user, $channel));
    }

    public function test_admin_can_view_private_channel(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::Administrator]);
        $channel = $this->createChannel(isPrivate: true);

        $this->assertTrue($this->service->userCanViewChannel($user, $channel));
    }

    // --- getAccessibleChannels ---

    public function test_get_accessible_channels_filters_private_channels(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $publicChannel = $this->createChannel(isPrivate: false);
        $privateChannel = $this->createChannel(isPrivate: true);

        $accessible = $this->service->getAccessibleChannels($user);

        $this->assertTrue($accessible->contains('id', $publicChannel->id));
        $this->assertFalse($accessible->contains('id', $privateChannel->id));
    }

    public function test_get_accessible_channels_includes_private_with_override(): void
    {
        [$user, $role] = $this->createUserWithRole([PermissionFlag::ViewChannels]);
        $privateChannel = $this->createChannel(isPrivate: true);

        ChannelPermissionOverride::factory()->create([
            'channel_id' => $privateChannel->id,
            'role_id' => $role->id,
            'allow' => [PermissionFlag::ViewChannels->value],
            'deny' => [],
        ]);

        $accessible = $this->service->getAccessibleChannels($user);

        $this->assertTrue($accessible->contains('id', $privateChannel->id));
    }

    // --- isAdministrator ---

    public function test_user_with_admin_role_is_administrator(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::Administrator]);

        $this->assertTrue($this->service->isAdministrator($user));
    }

    public function test_user_without_admin_role_is_not_administrator(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::SendMessages]);

        $this->assertFalse($this->service->isAdministrator($user));
    }

    // --- outranks ---

    public function test_user_with_higher_position_outranks(): void
    {
        [$actor] = $this->createUserWithRole([PermissionFlag::ManageRoles], position: 100);
        [$target] = $this->createUserWithRole([PermissionFlag::SendMessages], position: 10);

        $this->assertTrue($this->service->outranks($actor, $target));
    }

    public function test_user_with_equal_position_does_not_outrank(): void
    {
        [$actor] = $this->createUserWithRole([PermissionFlag::ManageRoles], position: 50);
        [$target] = $this->createUserWithRole([PermissionFlag::SendMessages], position: 50);

        $this->assertFalse($this->service->outranks($actor, $target));
    }

    public function test_user_with_lower_position_does_not_outrank(): void
    {
        [$actor] = $this->createUserWithRole([PermissionFlag::ManageRoles], position: 10);
        [$target] = $this->createUserWithRole([PermissionFlag::SendMessages], position: 100);

        $this->assertFalse($this->service->outranks($actor, $target));
    }

    // --- canManageRole ---

    public function test_admin_can_manage_role_below_their_position(): void
    {
        [$admin] = $this->createUserWithRole([PermissionFlag::Administrator], position: 100);
        $targetRole = Role::factory()->create(['position' => 50]);

        $this->assertTrue($this->service->canManageRole($admin, $targetRole));
    }

    public function test_admin_cannot_manage_role_at_their_position(): void
    {
        [$admin] = $this->createUserWithRole([PermissionFlag::Administrator], position: 100);
        $targetRole = Role::factory()->create(['position' => 100]);

        $this->assertFalse($this->service->canManageRole($admin, $targetRole));
    }

    public function test_admin_cannot_manage_role_above_their_position(): void
    {
        [$admin] = $this->createUserWithRole([PermissionFlag::Administrator], position: 50);
        $targetRole = Role::factory()->create(['position' => 100]);

        $this->assertFalse($this->service->canManageRole($admin, $targetRole));
    }

    public function test_non_admin_cannot_manage_any_role(): void
    {
        [$user] = $this->createUserWithRole([PermissionFlag::ManageRoles], position: 100);
        $targetRole = Role::factory()->create(['position' => 10]);

        $this->assertFalse($this->service->canManageRole($user, $targetRole));
    }
}
