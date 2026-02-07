<?php

namespace Tests\Feature\Models;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_group_can_be_created(): void
    {
        $group = DirectMessageGroup::factory()->create();

        $this->assertDatabaseHas('direct_message_groups', ['id' => $group->id]);
    }

    public function test_dm_group_has_owner(): void
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($group->owner->is($user));
    }

    public function test_dm_group_has_participants(): void
    {
        $users = User::factory(3)->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $users[0]->id]);
        $group->participants()->attach($users->pluck('id'));

        $this->assertCount(3, $group->participants);
    }

    public function test_dm_group_has_messages(): void
    {
        $group = DirectMessageGroup::factory()->create();
        DirectMessage::factory()->create(['direct_message_group_id' => $group->id]);

        $this->assertCount(1, $group->messages);
    }

    public function test_one_to_one_dm_detection(): void
    {
        $users = User::factory(2)->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $users[0]->id]);
        $group->participants()->attach($users->pluck('id'));

        $this->assertTrue($group->isOneToOne());
    }

    public function test_group_dm_is_not_one_to_one(): void
    {
        $users = User::factory(4)->create();
        $group = DirectMessageGroup::factory()->create(['owner_id' => $users[0]->id]);
        $group->participants()->attach($users->pluck('id'));

        $this->assertFalse($group->isOneToOne());
    }

    public function test_direct_message_belongs_to_group(): void
    {
        $group = DirectMessageGroup::factory()->create();
        $message = DirectMessage::factory()->create([
            'direct_message_group_id' => $group->id,
        ]);

        $this->assertTrue($message->group->is($group));
    }

    public function test_direct_message_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $message = DirectMessage::factory()->for($user)->create();

        $this->assertTrue($message->user->is($user));
    }

    public function test_direct_message_can_be_soft_deleted(): void
    {
        $message = DirectMessage::factory()->create();

        $message->delete();

        $this->assertSoftDeleted('direct_messages', ['id' => $message->id]);
    }

    public function test_direct_message_edited_state(): void
    {
        $message = DirectMessage::factory()->edited()->create();

        $this->assertTrue($message->is_edited);
        $this->assertNotNull($message->edited_at);
    }

    public function test_dm_group_named_factory_state(): void
    {
        $group = DirectMessageGroup::factory()->named()->create();

        $this->assertNotNull($group->name);
    }

    public function test_dm_group_tracks_last_read(): void
    {
        $user = User::factory()->create();
        $group = DirectMessageGroup::factory()->create();
        $group->participants()->attach($user->id, ['last_read_at' => now()]);

        $pivot = $group->participants()->where('user_id', $user->id)->first()->pivot;

        $this->assertNotNull($pivot->last_read_at);
    }
}
