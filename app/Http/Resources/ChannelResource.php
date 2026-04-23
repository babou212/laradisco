<?php

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Channel */
class ChannelResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'topic' => $this->topic,
            'channel_type' => $this->type->value,
            'is_private' => $this->is_private,
            'category_id' => $this->category_id,
            'position' => $this->position,
            'slowmode_seconds' => $this->slowmode_seconds,
            'channelPermissions' => fn () => $this->when($this->channelPermissions !== null, $this->channelPermissions),
            'has_unread' => false,
            'created_at' => $this->created_at,
        ];
    }
}
