<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin User */
class UserSummaryResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'username' => $this->username,
            'display_name' => $this->display_name ?? $this->nickname ?? $this->name,
            'nickname' => $this->nickname,
            'avatar_urls' => $this->avatar_urls,
            'status' => $this->status ?? 'offline',
            'custom_status' => $this->custom_status,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @var array<string, class-string>
     */
    public $relationships = [
        'roles' => RoleResource::class,
    ];

    public function toType(Request $request): string
    {
        return 'users';
    }
}
