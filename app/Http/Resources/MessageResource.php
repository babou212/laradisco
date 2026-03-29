<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'user_id' => $this->user_id,
            'content' => $this->message_bytes,
            'sender_device_id' => $this->sender_device_id,
            'is_pinned' => $this->is_pinned ?? false,
            'is_edited' => $this->is_edited ?? false,
            'edited_at' => $this->edited_at?->toISOString(),
            'reply_to_id' => $this->reply_to_id,
            'thread_id' => $this->thread_id,
            'thread' => new ThreadResource($this->whenLoaded('threadStarted')),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'reply_to' => new self($this->whenLoaded('replyTo')),
            'reactions' => ReactionResource::collection($this->whenLoaded('reactions')),
            'attachments' => $this->whenLoaded('attachments'),
            'encrypted_attachments' => EncryptedAttachmentResource::collection($this->whenLoaded('encryptedAttachments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
