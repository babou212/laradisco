<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserSummaryResource extends JsonResource
{
    /**
     * A lightweight user representation for lists and references.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name ?? $this->nickname ?? $this->name,
            'avatar_urls' => $this->avatar_urls,
            'status' => $this->status ?? 'offline',
            'custom_status' => $this->custom_status,
        ];
    }
}
