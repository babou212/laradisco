<?php

namespace App\Http\Middleware;

use App\Enums\PermissionFlag;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? [
                    'canInviteMembers' => $user->isAdministrator() || $user->hasPermission(PermissionFlag::InviteMembers),
                    'canManageRoles' => $user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageRoles),
                    'canManageChannels' => $user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageChannels),
                    'canManageServer' => $user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageServer),
                    'canManageMessages' => $user->isAdministrator() || $user->hasPermission(PermissionFlag::ManageMessages),
                    'isAdministrator' => $user->isAdministrator(),
                ] : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'members' => fn () => $user
                ? User::query()
                    ->select('id', 'username', 'name', 'nickname', 'avatar_path', 'custom_status')
                    ->orderBy('username')
                    ->get()
                    ->map(fn (User $u) => [
                        'id' => $u->id,
                        'username' => $u->username,
                        'display_name' => $u->display_name,
                        'avatar_path' => $u->avatar_path,
                        'custom_status' => $u->custom_status,
                    ])
                : [],
        ];
    }
}
