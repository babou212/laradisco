<?php

namespace App\Http\Resources;

use App\Models\InviteLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin InviteLink */
class InviteLinkResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'token' => $this->token,
            'created_by' => $this->created_by,
            'used_by' => $this->used_by,
            'used_at' => $this->used_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The resource's relationships.
     */
    public $relationships = [
        'creator' => UserSummaryResource::class,
        'usedByUser' => UserSummaryResource::class,
    ];
}
