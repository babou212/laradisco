<?php

namespace App\Http\Resources;

use App\Models\DirectMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DirectMessage */
class DirectMessageResource extends JsonResource
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
            'dm_group_id' => $this->dm_group_id ?? $this->direct_message_group_id,
            'user_id' => $this->user_id,
            'content' => $this->message_bytes,
            'sender_device_id' => $this->sender_device_id,
            'reply_to_id' => $this->reply_to_id,
            'reply_to' => new self($this->whenLoaded('replyTo')),
            'is_pinned' => $this->is_pinned ?? false,
            'is_edited' => $this->is_edited ?? false,
            'edited_at' => $this->edited_at?->toISOString(),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'reactions' => ReactionResource::collection($this->whenLoaded('reactions')),
            'encrypted_attachments' => EncryptedAttachmentResource::collection($this->whenLoaded('encryptedAttachments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
