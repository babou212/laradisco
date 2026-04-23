<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Message */
class MessageResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'channel_id' => $this->channel_id,
            'user_id' => $this->user_id,
            'content' => $this->message_bytes,
            'sender_device_id' => $this->sender_device_id,
            'is_pinned' => $this->is_pinned ?? false,
            'is_edited' => $this->is_edited ?? false,
            'edited_at' => $this->edited_at,
            'reply_to_id' => $this->reply_to_id,
            'thread_id' => $this->thread_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the resource's relationships.
     *
     * @return array<string, class-string>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'user' => UserSummaryResource::class,
            'replyTo' => self::class,
            'threadStarted' => ThreadResource::class,
            'reactions' => ReactionResource::class,
            'encryptedAttachments' => EncryptedAttachmentResource::class,
        ];
    }
}
