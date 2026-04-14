<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin User */
class UserResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => fn () => $this->when($this->isCurrentUser($request), $this->email),
            'email_verified_at' => fn () => $this->when($this->isCurrentUser($request), $this->email_verified_at),
            'avatar_urls' => $this->avatar_urls,
            'display_name' => $this->display_name,
            'nickname' => $this->nickname,
            'status' => $this->status ?? 'offline',
            'custom_status' => $this->custom_status,
            'permissions' => fn () => $this->when($this->isCurrentUser($request), fn () => [
                'canInviteMembers' => $this->isAdministrator() || $this->hasPermissionTo('invite_members'),
                'canManageRoles' => $this->isAdministrator() || $this->hasPermissionTo('manage_roles'),
                'canManageChannels' => $this->isAdministrator() || $this->hasPermissionTo('manage_channels'),
                'canManageServer' => $this->isAdministrator() || $this->hasPermissionTo('manage_server'),
                'canManageMessages' => $this->isAdministrator() || $this->hasPermissionTo('manage_messages'),
                'canBanMembers' => $this->isAdministrator() || $this->hasPermissionTo('ban_members'),
                'canKickMembers' => $this->isAdministrator() || $this->hasPermissionTo('kick_members'),
                'canViewAuditLog' => $this->isAdministrator() || $this->hasPermissionTo('view_audit_log'),
                'isAdministrator' => $this->isAdministrator(),
                'isBanned' => $this->isBanned(),
                'isJailed' => $this->isJailed(),
            ]),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The resource's relationships.
     *
     * @var array<string, class-string>
     */
    public $relationships = [
        'roles' => RoleResource::class,
    ];

    /**
     * Check if this resource represents the currently authenticated user.
     */
    private function isCurrentUser(Request $request): bool
    {
        return $request->user()?->id === $this->id;
    }
}
