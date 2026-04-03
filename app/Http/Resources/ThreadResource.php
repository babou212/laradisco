<?php

namespace App\Http\Resources;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Thread */
class ThreadResource extends JsonApiResource
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
            'message_id' => $this->message_id,
            'name' => $this->name,
            'message_count' => $this->message_count,
            'last_message_at' => $this->last_message_at,
            'is_archived' => $this->is_archived,
            'is_locked' => $this->is_locked,
            'is_following' => fn () => $this->whenLoaded('followers', function () use ($request) {
                return $this->followers->contains('id', $request->user()?->id);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the resource's relationships.
     */
    public function toRelationships(Request $request): array
    {
        return [
            'user' => UserSummaryResource::class,
            'latestReply' => MessageResource::class,
        ];
    }
}
