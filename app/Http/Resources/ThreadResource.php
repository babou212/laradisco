<?php

namespace App\Http\Resources;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Thread */
class ThreadResource extends JsonResource
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
            'message_id' => $this->message_id,
            'name' => $this->name,
            'message_count' => $this->message_count,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'is_archived' => $this->is_archived,
            'is_locked' => $this->is_locked,
            'is_following' => $this->whenLoaded('followers', function () use ($request) {
                return $this->followers->contains('id', $request->user()?->id);
            }),
            'last_reply' => new MessageResource($this->whenLoaded('latestReply')),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
