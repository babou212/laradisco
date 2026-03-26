<?php

namespace App\Http\Resources;

use App\Models\DirectMessageGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DirectMessageGroup */
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

        if (! $lastMessage && $this->relationLoaded('messages')) {
            $lastMessage = $this->messages->first();
        }

        return [
            'id' => $this->id,
            'name' => $this->name ?? $otherParticipant->username ?? 'Unknown',
            'other_user' => $otherParticipant ? new UserSummaryResource($otherParticipant) : null,
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'created_at' => $lastMessage->created_at?->toISOString(),
                'user_id' => $lastMessage->user_id,
                'sender_device_id' => $lastMessage->sender_device_id,
            ] : null,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
