<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasting';

    public function __construct(public Message $message)
    {
        $this->message->load(['user', 'attachments']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('thread.'.$this->message->thread_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'channel_id' => $this->message->channel_id,
                'user_id' => $this->message->user_id,
                'thread_id' => $this->message->thread_id,
                'content' => $this->message->content,
                'reply_to_id' => $this->message->reply_to_id,
                'client_temp_id' => $this->message->client_temp_id,
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
