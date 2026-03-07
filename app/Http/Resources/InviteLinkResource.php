<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InviteLinkResource extends JsonResource
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
            'token' => $this->token,
            'created_by' => $this->created_by,
            'used_by' => $this->used_by,
            'used_at' => $this->used_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'used_by_user' => new UserSummaryResource($this->whenLoaded('usedByUser')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
