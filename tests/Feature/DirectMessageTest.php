<?php

namespace Tests\Feature;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_start_dm_with_another_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->actingAs($user1)->postJson('/direct-message/start', [
            'user_id' => $user2->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['dm_group_id']);

        $dmGroup = DirectMessageGroup::find($response->json('dm_group_id'));
        $this->assertNotNull($dmGroup);
        $this->assertTrue($dmGroup->participants->contains($user1));
        $this->assertTrue($dmGroup->participants->contains($user2));
    }

    public function test_cannot_start_dm_with_yourself(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/direct-message/start', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(400);
    }

    public function test_returns_existing_dm_if_already_exists(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $response = $this->actingAs($user1)->postJson('/direct-message/start', [
            'user_id' => $user2->id,
        ]);

        $response->assertOk();
        $this->assertEquals($dmGroup->id, $response->json('dm_group_id'));
    }

    public function test_can_send_message_in_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $response = $this->actingAs($user1)->post("/direct-message/{$dmGroup->id}/messages", [
            'content' => 'Hello!',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('direct_messages', [
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Hello!',
        ]);

        $dmGroup->refresh();
        $this->assertNotNull($dmGroup->last_message_at);
    }

    public function test_cannot_send_message_if_not_participant(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $response = $this->actingAs($outsider)->post("/direct-message/{$dmGroup->id}/messages", [
            'content' => 'Hello!',
        ]);

        $response->assertForbidden();
    }

    public function test_can_view_dm_messages(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Message 1',
        ]);

        DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user2->id,
            'content' => 'Message 2',
        ]);

        $response = $this->actingAs($user1)->get("/direct-message/{$dmGroup->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('DirectMessages')
            ->has('messages.data', 2)
        );
    }

    public function test_cannot_view_dm_if_not_participant(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $response = $this->actingAs($outsider)->getJson("/direct-message/{$dmGroup->id}");

        $response->assertForbidden();
    }

    public function test_can_update_own_message(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Original message',
        ]);

        $response = $this->actingAs($user1)->putJson("/direct-message/{$dmGroup->id}/messages/{$message->id}", [
            'content' => 'Updated message',
        ]);

        $response->assertOk();
        $message->refresh();
        $this->assertEquals('Updated message', $message->content);
        $this->assertTrue($message->is_edited);
    }

    public function test_cannot_update_others_message(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Original message',
        ]);

        $response = $this->actingAs($user2)->putJson("/direct-message/{$dmGroup->id}/messages/{$message->id}", [
            'content' => 'Hacked message',
        ]);

        $response->assertForbidden();
    }

    public function test_can_delete_own_message(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'To be deleted',
        ]);

        $response = $this->actingAs($user1)->deleteJson("/direct-message/{$dmGroup->id}/messages/{$message->id}");

        $response->assertOk();
        $this->assertSoftDeleted('direct_messages', ['id' => $message->id]);
    }

    public function test_cannot_delete_others_message(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Protected message',
        ]);

        $response = $this->actingAs($user2)->deleteJson("/direct-message/{$dmGroup->id}/messages/{$message->id}");

        $response->assertForbidden();
    }
}
