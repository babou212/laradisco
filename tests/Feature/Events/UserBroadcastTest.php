<?php

namespace Tests\Feature\Events;

use App\Enums\PermissionFlag;
use App\Events\UserProfileUpdated;
use App\Events\UserRolesUpdated;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['color' => '#E74C3C', 'is_hoisted' => true, 'position' => 100, 'is_mentionable' => true, 'is_default' => false],
        );
        $adminRole->givePermissionTo(PermissionFlag::Administrator->value);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_assign_role_broadcasts_user_roles_updated(): void
    {
        Event::fake([UserRolesUpdated::class]);

        $admin = $this->makeAdmin();
        $target = User::factory()->create();
        $role = Role::factory()->create(['name' => 'TestRole_'.uniqid(), 'position' => 50]);

        $response = $this->actingAs($admin)
            ->postJson(route('api.settings.members.roles.assign', $target), [
                'role_id' => $role->id,
            ]);

        $response->assertOk();

        Event::assertDispatched(UserRolesUpdated::class, function (UserRolesUpdated $event) use ($target, $role) {
            if ($event->user->id !== $target->id) {
                return false;
            }
            $payload = $event->broadcastWith();

            return $payload['user_id'] === $target->id
                && is_array($payload['roles'])
                && collect($payload['roles'])->contains(fn ($r) => $r['id'] === (string) $role->id && $r['name'] === $role->name)
                && array_key_exists('permissions', $payload)
                && array_key_exists('isAdministrator', $payload['permissions']);
        });
    }

    public function test_remove_role_broadcasts_user_roles_updated(): void
    {
        Event::fake([UserRolesUpdated::class]);

        $admin = $this->makeAdmin();
        $target = User::factory()->create();
        $role = Role::factory()->create(['name' => 'TestRole_'.uniqid(), 'position' => 50]);
        $target->assignRole($role);

        $response = $this->actingAs($admin)
            ->deleteJson(route('api.settings.members.roles.remove', [$target, $role]));

        $response->assertNoContent();

        Event::assertDispatched(UserRolesUpdated::class, function (UserRolesUpdated $event) use ($target, $role) {
            $payload = $event->broadcastWith();

            return $event->user->id === $target->id
                && $payload['user_id'] === $target->id
                && collect($payload['roles'])->every(fn ($r) => $r['name'] !== $role->name);
        });
    }

    public function test_user_roles_updated_broadcasts_on_presence_channel_as_user_roles_updated(): void
    {
        $user = User::factory()->create();
        $event = new UserRolesUpdated($user);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('private-presence', $channels[0]->name);
        $this->assertSame('user.roles.updated', $event->broadcastAs());
    }

    public function test_profile_update_broadcasts_about_me(): void
    {
        Event::fake([UserProfileUpdated::class]);

        $user = User::factory()->create([
            'email' => 'orig@example.com',
            'about_me' => 'original bio',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.settings.profile.update'), [
                'email' => 'updated@example.com',
                'about_me' => 'updated bio',
            ]);

        $response->assertOk();

        Event::assertDispatched(UserProfileUpdated::class, function (UserProfileUpdated $event) use ($user) {
            $payload = $event->broadcastWith();

            return $event->user->id === $user->id
                && $payload['about_me'] === 'updated bio'
                && array_key_exists('avatar_urls', $payload)
                && array_key_exists('display_name', $payload);
        });
    }

    public function test_queued_avatar_conversions_broadcast_profile_update_once_all_are_generated(): void
    {
        $this->fakeS3();
        Config::set('media-library.queue_conversions_by_default', true);
        Event::fake([UserProfileUpdated::class]);

        $user = User::factory()->create();

        $user->addMedia(UploadedFile::fake()->image('avatar.png', 256, 256))
            ->toMediaCollection('avatar');

        $media = $user->fresh()->getFirstMedia('avatar');

        $this->assertTrue($media->hasGeneratedConversion('thumb'));
        $this->assertTrue($media->hasGeneratedConversion('small'));
        $this->assertTrue($media->hasGeneratedConversion('medium'));

        Event::assertDispatched(UserProfileUpdated::class, function (UserProfileUpdated $event) use ($user) {
            return $event->user->id === $user->id
                && $event->broadcastWith()['avatar_urls'] !== null;
        });
    }
}
