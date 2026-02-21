<?php

namespace Tests\Feature;

use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\DirectMessageReaction;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    // --- Reaction tests ---

    public function test_participant_can_add_reaction_to_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user2->id,
            'content' => 'React to this!',
        ]);

        $response = $this->actingAs($user1)
            ->postJson("/direct-message/{$dmGroup->id}/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertOk();
        $response->assertJsonPath('added', true);

        $this->assertDatabaseHas('direct_message_reactions', [
            'direct_message_id' => $message->id,
            'user_id' => $user1->id,
            'emoji' => '👍',
        ]);
    }

    public function test_participant_can_remove_reaction_from_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user2->id,
            'content' => 'React to this!',
        ]);

        DirectMessageReaction::create([
            'direct_message_id' => $message->id,
            'user_id' => $user1->id,
            'emoji' => '👍',
        ]);

        $response = $this->actingAs($user1)
            ->postJson("/direct-message/{$dmGroup->id}/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertOk();
        $response->assertJsonPath('added', false);

        $this->assertDatabaseMissing('direct_message_reactions', [
            'direct_message_id' => $message->id,
            'user_id' => $user1->id,
            'emoji' => '👍',
        ]);
    }

    public function test_non_participant_cannot_react_to_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Private message',
        ]);

        $response = $this->actingAs($outsider)
            ->postJson("/direct-message/{$dmGroup->id}/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertForbidden();
    }

    public function test_dm_reaction_requires_emoji(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $user1->id]);
        $dmGroup->participants()->attach([$user1->id, $user2->id]);

        $message = DirectMessage::create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $user1->id,
            'content' => 'Test message',
        ]);

        $response = $this->actingAs($user1)
            ->postJson("/direct-message/{$dmGroup->id}/messages/{$message->id}/reactions", [
                'emoji' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('emoji');
    }

    // --- DM Notification Tests ---

    public function test_sending_dm_notifies_other_participants(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $sender->id]);
        $dmGroup->participants()->attach([$sender->id, $recipient->id]);

        Notification::fake();

        $this->actingAs($sender)->post("/direct-message/{$dmGroup->id}/messages", [
            'content' => 'Hey there!',
        ]);

        Notification::assertSentTo($recipient, DirectMessageNotification::class);
        Notification::assertNotSentTo($sender, DirectMessageNotification::class);
    }

    public function test_dm_notification_is_sent_regardless_of_recipient_status(): void
    {
        $sender = User::factory()->create();
        $offlineRecipient = User::factory()->create(['status' => 'offline']);

        $dmGroup = DirectMessageGroup::create(['owner_id' => $sender->id]);
        $dmGroup->participants()->attach([$sender->id, $offlineRecipient->id]);

        Notification::fake();

        $this->actingAs($sender)->post("/direct-message/{$dmGroup->id}/messages", [
            'content' => 'Are you there?',
        ]);

        Notification::assertSentTo($offlineRecipient, DirectMessageNotification::class);
    }

    public function test_dm_notification_contains_expected_data(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $dmGroup = DirectMessageGroup::create(['owner_id' => $sender->id]);
        $dmGroup->participants()->attach([$sender->id, $recipient->id]);

        $message = DirectMessage::factory()->create([
            'direct_message_group_id' => $dmGroup->id,
            'user_id' => $sender->id,
            'content' => 'Hello from DM!',
        ]);

        $notification = new DirectMessageNotification($message);
        $data = $notification->toArray($recipient);

        $this->assertEquals($message->id, $data['message_id']);
        $this->assertEquals($dmGroup->id, $data['dm_group_id']);
        $this->assertEquals($sender->id, $data['sender_id']);
        $this->assertEquals($sender->username, $data['sender_username']);
        $this->assertEquals('Hello from DM!', $data['content']);
        $this->assertEquals('direct_message', $data['notification_type']);
    }
}
