<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ThreadReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Message $message,
    ) {
        $this->onQueue('notifications');
        $this->message->loadMissing(['user', 'channel', 'thread']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'channel_id' => $this->message->channel_id,
            'channel_name' => $this->message->channel?->name,
            'thread_id' => $this->message->thread_id,
            'thread_name' => $this->message->thread?->name,
            'sender_id' => $this->message->user_id,
            'sender_username' => $this->message->user?->username,
            'sender_avatar' => $this->message->user?->avatar_urls['thumb'] ?? null,
            'content' => Str::limit((string) $this->message->content, 120),
            'notification_type' => 'thread_reply',
        ];
    }
}
