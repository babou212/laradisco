<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The queue the broadcast event should be placed on.
     */
    public string $queue = 'broadcasting';

    /**
     * Create a new event instance.
     */
    public function __construct(public DirectMessage $message)
    {
        $this->message->load(['user', 'attachments']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('direct-message.'.$this->message->direct_message_group_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'App\Events\MessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'dm_group_id' => $this->message->direct_message_group_id,
                'user_id' => $this->message->user_id,
                'content' => $this->message->content,
                'link_preview' => $this->message->link_preview,
                'reply_to_id' => $this->message->reply_to_id,
                'is_edited' => $this->message->is_edited ?? false,
                'client_temp_id' => $this->message->client_temp_id,
                'reactions' => [],
                'attachments' => $this->message->attachments->map(fn ($a) => [
                    'id' => $a->uuid,
                    'file_name' => $a->file_name,
                    'mime_type' => $a->mime_type,
                    'size' => $a->size,
                    'has_thumbnail' => $a->hasGeneratedConversion('thumb'),
                ])->all(),
                'user' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'username' => $this->message->user->username,
                    'avatar_urls' => $this->message->user->avatar_urls,
                ] : null,
                'created_at' => $this->message->created_at?->toISOString(),
                'updated_at' => $this->message->updated_at?->toISOString(),
            ],
        ];
    }
}
