<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'content' => $this->content,
            'is_edited' => $this->is_edited ?? false,
            'edited_at' => $this->edited_at?->toISOString(),
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'reactions' => ReactionResource::collection($this->whenLoaded('reactions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
