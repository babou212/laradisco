<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelResource extends JsonResource
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
            'name' => $this->name,
            'topic' => $this->topic,
            'type' => $this->type instanceof \App\Enums\ChannelType ? $this->type->value : $this->type,
            'is_private' => $this->is_private,
            'category_id' => $this->category_id,
            'position' => $this->position,
            'slowmode_seconds' => $this->slowmode_seconds,
            'permissions' => $this->when($this->channelPermissions !== null, $this->channelPermissions),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
