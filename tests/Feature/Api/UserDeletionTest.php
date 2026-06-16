<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionFlag;
use App\Events\UserDeleted;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function admin(int $position = 100): User
    {
        $role = Role::factory()->create([
            'permissions' => [PermissionFlag::Administrator->value],
            'position' => $position,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_guest_cannot_delete_a_user(): void
    {
        $target = User::factory()->create();

        $this->deleteJson(route('api.settings.moderation.delete-user', $target))
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_delete_a_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson(route('api.settings.moderation.delete-user', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_delete_a_user_with_an_equal_or_higher_role(): void
    {
        $admin = $this->admin(position: 50);
        $peer = $this->admin(position: 50);

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $peer))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $peer->id]);
    }

    public function test_admin_deletes_user_but_their_messages_survive_as_tombstones(): void
    {
        Event::fake([UserDeleted::class]);

        $admin = $this->admin();
        $target = User::factory()->create(['username' => 'ghostwriter']);
        $message = Message::factory()->create(['user_id' => $target->id]);

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $target))
            ->assertOk();

        // The account is gone...
        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        // ...but the message remains, with the author nulled and name snapshotted.
        $message->refresh();
        $this->assertNull($message->user_id);
        $this->assertSame('ghostwriter', $message->deleted_author_name);

        Event::assertDispatched(UserDeleted::class, fn (UserDeleted $e) => $e->userId === $target->id
            && $e->username === 'ghostwriter');
    }

    public function test_soft_deleted_messages_also_keep_the_tombstone_name(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['username' => 'gone']);
        $message = Message::factory()->create(['user_id' => $target->id]);
        $message->delete();

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $target))
            ->assertOk();

        $row = Message::withTrashed()->find($message->id);
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);
        $this->assertSame('gone', $row->deleted_author_name);
    }

    public function test_reactions_by_deleted_user_are_retained_anonymously(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $message = Message::factory()->create();
        $reaction = MessageReaction::factory()->create([
            'message_id' => $message->id,
            'user_id' => $target->id,
            'emoji' => '👍',
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $target))
            ->assertOk();

        $reaction->refresh();
        $this->assertNull($reaction->user_id);
        $this->assertSame('👍', $reaction->emoji);
    }

    public function test_owned_dm_group_is_reassigned_so_messages_survive(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['username' => 'owner']);
        $other = User::factory()->create();

        $group = DirectMessageGroup::factory()->create(['owner_id' => $owner->id]);
        $group->participants()->attach([$owner->id, $other->id]);
        $dm = DirectMessage::factory()->create([
            'direct_message_group_id' => $group->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $owner))
            ->assertOk();

        // Group survives, ownership transferred, message retained as tombstone.
        $this->assertDatabaseHas('direct_message_groups', [
            'id' => $group->id,
            'owner_id' => $other->id,
        ]);
        $dm->refresh();
        $this->assertNull($dm->user_id);
        $this->assertSame('owner', $dm->deleted_author_name);
    }

    public function test_deletion_revokes_tokens_and_writes_audit_log(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['username' => 'tokenholder']);
        $target->createToken('test');

        $this->actingAs($admin)
            ->deleteJson(route('api.settings.moderation.delete-user', $target))
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->id,
            'tokenable_type' => User::class,
        ]);

        $this->assertDatabaseHas('moderation_audit_log', [
            'actor_id' => $admin->id,
            'action' => 'delete_user',
        ]);
    }
}
