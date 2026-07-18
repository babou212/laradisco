<?php

namespace App\Notifications;

use App\Models\DirectMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DirectMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DirectMessage $message,
    ) {
        $this->onQueue('notifications');
        $this->message->loadMissing(['user', 'group']);
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
            'dm_group_id' => $this->message->direct_message_group_id,
            'dm_group_name' => $this->message->group?->name,
            'sender_id' => $this->message->user_id,
            'sender_username' => $this->message->user?->username,
            'sender_avatar' => $this->message->user?->avatar_urls['thumb'] ?? null,
            'content' => Str::limit((string) $this->message->content, 120),
            'message_bytes' => $this->message->message_bytes,
            'epoch' => $this->message->epoch,
            'sender_device_id' => $this->message->sender_device_id,
            'notification_type' => 'direct_message',
        ];
    }
}
