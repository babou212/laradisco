<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReactionResource extends JsonResource
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
            'user_id' => $this->user_id,
            'emoji' => $this->emoji,
            'message_id' => $this->message_id ?? $this->reactable_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
