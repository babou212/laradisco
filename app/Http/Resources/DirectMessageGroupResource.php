<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DirectMessageGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $otherParticipant = $this->whenLoaded('participants', function () use ($currentUser) {
            return $this->participants->firstWhere('id', '!=', $currentUser?->id);
        });

        $lastMessage = $this->relationLoaded('lastMessage') ? $this->lastMessage : null;
        // Fallback for eager load via 'messages' relation
        if (! $lastMessage && $this->relationLoaded('messages')) {
            $lastMessage = $this->messages->first();
        }

        return [
            'id' => $this->id,
            'name' => $this->name ?? $otherParticipant?->username ?? 'Unknown',
            'other_user' => $otherParticipant ? new UserSummaryResource($otherParticipant) : null,
            'last_message' => $lastMessage ? [
                'content' => $lastMessage->content,
                'created_at' => $lastMessage->created_at?->toISOString(),
                'user_id' => $lastMessage->user_id,
                'is_encrypted' => (bool) $lastMessage->is_encrypted,
                'sender_device_id' => $lastMessage->sender_device_id,
            ] : null,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
