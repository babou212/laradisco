<?php

namespace App\Http\Resources;

use App\Models\DirectMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin DirectMessage */
class DirectMessageResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'dm_group_id' => $this->dm_group_id ?? $this->direct_message_group_id,
            'user_id' => $this->user_id,
            'deleted_author_name' => $this->deleted_author_name,
            'content' => $this->content,
            'link_preview' => $this->link_preview,
            'reply_to_id' => $this->reply_to_id,
            'is_pinned' => $this->is_pinned ?? false,
            'is_edited' => $this->is_edited ?? false,
            'edited_at' => $this->edited_at,
            'client_temp_id' => $this->client_temp_id,
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
            'reactions' => ReactionResource::class,
            'attachments' => AttachmentResource::class,
        ];
    }
}
