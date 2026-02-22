<?php

namespace Tests\Feature;

use App\Enums\PermissionFlag;
use App\Enums\UserStatusType;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Services\MentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MentionTest extends TestCase
{
    use RefreshDatabase;

    private function createChannelSetup(array $extraPermissions = []): array
    {
        $user = User::factory()->create();
        $permissions = array_map(
            fn (PermissionFlag $p) => $p->value,
            PermissionFlag::defaultEveryonePermissions()
        );
        foreach ($extraPermissions as $perm) {
            $permissions[] = $perm->value;
        }
        $role = Role::factory()->create(['permissions' => $permissions]);
        $user->roles()->attach($role);
        $category = Category::factory()->create();
        $channel = Channel::factory()->create(['category_id' => $category->id]);

        return [$user, $channel];
    }

    // --- Mention Parsing Tests ---

    public function test_sending_message_with_user_mention_creates_mention_record(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $mentionedUser = User::factory()->create(['username' => 'johndoe']);

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hey @johndoe check this out!',
            ]);

        $this->assertDatabaseHas('mentions', [
            'user_id' => $mentionedUser->id,
            'type' => 'user',
        ]);
    }

    public function test_sending_message_with_everyone_mention_creates_mention_record(): void
    {
        [$sender, $channel] = $this->createChannelSetup([PermissionFlag::MentionEveryone]);
        User::factory()->count(3)->create();

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Attention @everyone, meeting in 5!',
            ]);

        $this->assertDatabaseHas('mentions', [
            'user_id' => null,
            'type' => 'everyone',
        ]);
    }

    public function test_sending_message_with_here_mention_creates_mention_record(): void
    {
        [$sender, $channel] = $this->createChannelSetup([PermissionFlag::MentionEveryone]);

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hey @here anyone available?',
            ]);

        $this->assertDatabaseHas('mentions', [
            'user_id' => null,
            'type' => 'here',
        ]);
    }

    public function test_user_mention_sends_notification_to_mentioned_user(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $mentionedUser = User::factory()->create(['username' => 'janedoe']);

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hello @janedoe!',
            ]);

        Notification::assertSentTo($mentionedUser, MentionNotification::class);
    }

    public function test_user_mention_does_not_notify_the_sender(): void
    {
        [$sender, $channel] = $this->createChannelSetup();

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Talking to myself @'.$sender->username,
            ]);

        Notification::assertNotSentTo($sender, MentionNotification::class);
    }

    public function test_everyone_mention_notifies_all_other_users(): void
    {
        [$sender, $channel] = $this->createChannelSetup([PermissionFlag::MentionEveryone]);
        $users = User::factory()->count(3)->create();

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => '@everyone important announcement!',
            ]);

        foreach ($users as $user) {
            Notification::assertSentTo($user, MentionNotification::class);
        }

        Notification::assertNotSentTo($sender, MentionNotification::class);
    }

    public function test_here_mention_notifies_only_online_users(): void
    {
        [$sender, $channel] = $this->createChannelSetup([PermissionFlag::MentionEveryone]);

        $onlineUser = User::factory()->create(['status' => UserStatusType::Online]);
        $idleUser = User::factory()->create(['status' => UserStatusType::Idle]);
        $offlineUser = User::factory()->create(['status' => UserStatusType::Offline]);
        $dndUser = User::factory()->create(['status' => UserStatusType::DoNotDisturb]);

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hey @here anyone around?',
            ]);

        Notification::assertSentTo($onlineUser, MentionNotification::class);
        Notification::assertSentTo($idleUser, MentionNotification::class);
        Notification::assertSentTo($dndUser, MentionNotification::class);
        Notification::assertNotSentTo($offlineUser, MentionNotification::class);
        Notification::assertNotSentTo($sender, MentionNotification::class);
    }

    public function test_multiple_user_mentions_in_one_message(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $user1 = User::factory()->create(['username' => 'alice']);
        $user2 = User::factory()->create(['username' => 'bob']);

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hey @alice and @bob check this',
            ]);

        Notification::assertSentTo($user1, MentionNotification::class);
        Notification::assertSentTo($user2, MentionNotification::class);

        $this->assertDatabaseCount('mentions', 2);
    }

    public function test_message_without_mentions_creates_no_mention_records(): void
    {
        [$sender, $channel] = $this->createChannelSetup();

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Just a regular message, no mentions.',
            ]);

        $this->assertDatabaseCount('mentions', 0);
        Notification::assertNothingSent();
    }

    public function test_mention_with_nonexistent_username_creates_no_mention(): void
    {
        [$sender, $channel] = $this->createChannelSetup();

        Notification::fake();

        $this->actingAs($sender)
            ->post(route('channels.messages.store', $channel), [
                'content' => 'Hey @nonexistentuser123 where are you?',
            ]);

        $this->assertDatabaseCount('mentions', 0);
        Notification::assertNothingSent();
    }

    // --- Mention Search API Tests ---

    public function test_mention_search_returns_matching_users(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'testuser']);
        User::factory()->create(['username' => 'testfoo']);
        User::factory()->create(['username' => 'other']);

        $response = $this->actingAs($user)
            ->getJson(route('api.mentions.search', ['q' => 'test']));

        $response->assertOk();
        $response->assertJsonCount(2, 'users');
    }

    public function test_mention_search_excludes_current_user(): void
    {
        $user = User::factory()->create(['username' => 'testme']);
        User::factory()->create(['username' => 'testother']);

        $response = $this->actingAs($user)
            ->getJson(route('api.mentions.search', ['q' => 'test']));

        $response->assertOk();
        $response->assertJsonCount(1, 'users');
        $response->assertJsonMissing(['username' => 'testme']);
    }

    public function test_mention_search_requires_query(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.mentions.search'));

        $response->assertUnprocessable();
    }

    // --- Notification API Tests ---

    public function test_user_can_fetch_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.notifications.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'notifications',
            'unread_count',
        ]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $recipient = User::factory()->create(['username' => 'recipient']);

        // Create a message with mention to generate notification
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $sender->id,
            'content' => 'Hey @recipient!',
        ]);

        $recipient->notify(new MentionNotification($message, 'user'));

        $notification = $recipient->notifications()->first();

        $response = $this->actingAs($recipient)
            ->postJson(route('api.notifications.markAsRead', $notification->id));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $recipient = User::factory()->create(['username' => 'recipient2']);

        $message1 = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $sender->id,
            'content' => 'Hey @recipient2!',
        ]);

        $message2 = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $sender->id,
            'content' => 'Again @recipient2!',
        ]);

        $recipient->notify(new MentionNotification($message1, 'user'));
        $recipient->notify(new MentionNotification($message2, 'user'));

        $this->assertEquals(2, $recipient->unreadNotifications()->count());

        $response = $this->actingAs($recipient)
            ->postJson(route('api.notifications.markAllAsRead'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);

        $this->assertEquals(0, $recipient->fresh()->unreadNotifications()->count());
    }

    // --- MentionService Extract Tests ---

    public function test_extract_mentions_parses_usernames(): void
    {
        $result = MentionService::extractMentions('Hey @alice and @bob!');

        $this->assertContains('alice', $result['usernames']);
        $this->assertContains('bob', $result['usernames']);
        $this->assertFalse($result['hasEveryone']);
        $this->assertFalse($result['hasHere']);
    }

    public function test_extract_mentions_detects_everyone(): void
    {
        $result = MentionService::extractMentions('Attention @everyone!');

        $this->assertTrue($result['hasEveryone']);
        $this->assertFalse($result['hasHere']);
    }

    public function test_extract_mentions_detects_here(): void
    {
        $result = MentionService::extractMentions('Hey @here check this');

        $this->assertFalse($result['hasEveryone']);
        $this->assertTrue($result['hasHere']);
    }

    public function test_extract_mentions_handles_no_mentions(): void
    {
        $result = MentionService::extractMentions('Just a regular message.');

        $this->assertEmpty($result['usernames']);
        $this->assertFalse($result['hasEveryone']);
        $this->assertFalse($result['hasHere']);
    }

    // --- Notification Data Structure Tests ---

    public function test_mention_notification_contains_expected_data(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $recipient = User::factory()->create(['username' => 'notifuser']);

        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $sender->id,
            'content' => 'Hey @notifuser!',
        ]);

        $notification = new MentionNotification($message, 'user');
        $data = $notification->toArray($recipient);

        $this->assertEquals($message->id, $data['message_id']);
        $this->assertEquals($channel->id, $data['channel_id']);
        $this->assertEquals($channel->name, $data['channel_name']);
        $this->assertEquals($sender->id, $data['sender_id']);
        $this->assertEquals($sender->username, $data['sender_username']);
        $this->assertEquals($message->content, $data['content']);
        $this->assertEquals('user', $data['mention_type']);
    }

    public function test_mention_notification_stores_correct_data_in_database(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $recipient = User::factory()->create(['username' => 'dbuser']);

        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $sender->id,
            'content' => 'Hey @dbuser!',
        ]);

        $recipient->notify(new MentionNotification($message, 'user'));

        $stored = $recipient->notifications()->first();
        $this->assertNotNull($stored);
        $this->assertEquals($message->id, $stored->data['message_id']);
        $this->assertEquals($channel->id, $stored->data['channel_id']);
        $this->assertEquals('user', $stored->data['mention_type']);
    }

    public function test_notifications_api_returns_correct_structure(): void
    {
        [$sender, $channel] = $this->createChannelSetup();
        $recipient = User::factory()->create(['username' => 'apiuser']);

        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'user_id' => $sender->id,
            'content' => 'Hey @apiuser!',
        ]);

        $recipient->notify(new MentionNotification($message, 'user'));

        $response = $this->actingAs($recipient)
            ->getJson(route('api.notifications.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'notifications');
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonStructure([
            'notifications' => [
                [
                    'id',
                    'type',
                    'data' => [
                        'message_id',
                        'channel_id',
                        'channel_name',
                        'sender_id',
                        'sender_username',
                        'content',
                        'mention_type',
                    ],
                    'read_at',
                    'created_at',
                ],
            ],
        ]);
    }
}
