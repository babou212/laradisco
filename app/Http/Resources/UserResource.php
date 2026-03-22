<?php

namespace App\Http\Resources;

use App\Enums\PermissionFlag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->when($this->isCurrentUser($request), $this->email),
            'email_verified_at' => $this->when($this->isCurrentUser($request), $this->email_verified_at),
            'avatar_path' => $this->avatar_path,
            'display_name' => $this->display_name,
            'nickname' => $this->nickname,
            'status' => $this->status ?? 'offline',
            'custom_status' => $this->custom_status,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->when($this->isCurrentUser($request), fn () => [
                'canInviteMembers' => $this->isAdministrator() || $this->hasPermission(PermissionFlag::InviteMembers),
                'canManageRoles' => $this->isAdministrator() || $this->hasPermission(PermissionFlag::ManageRoles),
                'canManageChannels' => $this->isAdministrator() || $this->hasPermission(PermissionFlag::ManageChannels),
                'canManageServer' => $this->isAdministrator() || $this->hasPermission(PermissionFlag::ManageServer),
                'canManageMessages' => $this->isAdministrator() || $this->hasPermission(PermissionFlag::ManageMessages),
                'isAdministrator' => $this->isAdministrator(),
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Check if this resource represents the currently authenticated user.
     */
    private function isCurrentUser(Request $request): bool
    {
        return $request->user()?->id === $this->id;
    }
}
