<?php

namespace Tests\Feature\Notifications;

use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Notifications\MentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_message_notification_includes_message_content(): void
    {
        $sender = User::factory()->create();
        $message = DirectMessage::factory()->create([
            'user_id' => $sender->id,
            'content' => 'hello bob',
        ]);

        $payload = (new DirectMessageNotification($message))->toArray(User::factory()->create());

        $this->assertSame('hello bob', $payload['content']);
        $this->assertSame($sender->username, $payload['sender_username']);
    }

    public function test_mention_notification_includes_message_content(): void
    {
        $sender = User::factory()->create();
        $message = Message::factory()->create([
            'user_id' => $sender->id,
            'content' => 'hey @bob',
        ]);

        $payload = (new MentionNotification($message))->toArray(User::factory()->create());

        $this->assertSame('hey @bob', $payload['content']);
        $this->assertSame($sender->username, $payload['sender_username']);
    }

    public function test_long_content_is_truncated_to_120_chars(): void
    {
        $message = DirectMessage::factory()->create([
            'content' => str_repeat('a', 300),
        ]);

        $payload = (new DirectMessageNotification($message))->toArray(User::factory()->create());

        $this->assertSame(Str::limit(str_repeat('a', 300), 120), $payload['content']);
        $this->assertStringEndsWith('...', $payload['content']);
    }

    public function test_null_content_becomes_empty_string(): void
    {
        $message = DirectMessage::factory()->create([
            'content' => null,
        ]);

        $payload = (new DirectMessageNotification($message))->toArray(User::factory()->create());

        $this->assertSame('', $payload['content']);
    }
}
