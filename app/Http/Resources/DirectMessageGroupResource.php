<?php

namespace App\Http\Resources;

use App\Models\DirectMessageGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin DirectMessageGroup */
class DirectMessageGroupResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
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
            'name' => $this->name ?? $otherParticipant->username ?? 'Unknown',
            'other_user' => fn () => $otherParticipant ? [
                'id' => (string) $otherParticipant->id,
                'username' => $otherParticipant->username,
                'display_name' => $otherParticipant->display_name ?? $otherParticipant->nickname ?? $otherParticipant->name,
                'avatar_urls' => $otherParticipant->avatar_urls,
                'status' => $otherParticipant->status ?? 'offline',
                'custom_status' => $otherParticipant->custom_status,
            ] : null,
            'last_message' => fn () => $lastMessage ? [
                'id' => $lastMessage->id,
                'created_at' => $lastMessage->created_at,
                'user_id' => $lastMessage->user_id,
                'sender_device_id' => $lastMessage->sender_device_id,
            ] : null,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The resource's relationships.
     *
     * @var array<string, class-string>
     */
    public $relationships = [
        'participants' => UserSummaryResource::class,
    ];
}
