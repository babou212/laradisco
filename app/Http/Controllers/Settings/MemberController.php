<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Display the members management page.
     */
    public function index(Request $request): Response
    {
        $this->authorizeMemberAccess($request);

        $members = User::query()
            ->with(['roles' => function ($query) {
                $query->orderByDesc('position');
            }])
            ->orderBy('username')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_path' => $user->avatar_path,
                'display_name' => $user->display_name,
                'roles' => $user->roles->unique('id')->values()->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'color' => $role->color,
                    'position' => $role->position,
                ]),
            ]);

        $roles = Role::query()
            ->orderByDesc('position')
            ->get(['id', 'name', 'color', 'position', 'is_default']);

        return Inertia::render('settings/Members', [
            'members' => $members,
            'roles' => $roles,
        ]);
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeMemberAccess($request);

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if (! $this->permissionService->canManageRole($request->user(), $role)) {
            abort(403, 'You cannot assign a role at or above your own position.');
        }

        if ($request->user()->id !== $user->id && ! $this->permissionService->outranks($request->user(), $user)) {
            abort(403, 'You cannot manage a user with an equal or higher role.');
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->permissionService->clearUserChannelCaches($user);

        return redirect()->route('settings.members.index');
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeMemberAccess($request);

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if ($role->is_default) {
            abort(403, 'Cannot remove the default role from a user.');
        }

        if (! $this->permissionService->canManageRole($request->user(), $role)) {
            abort(403, 'You cannot remove a role at or above your own position.');
        }

        if ($request->user()->id !== $user->id && ! $this->permissionService->outranks($request->user(), $user)) {
            abort(403, 'You cannot manage a user with an equal or higher role.');
        }

        $user->roles()->detach($role->id);

        $this->permissionService->clearUserChannelCaches($user);

        return redirect()->route('settings.members.index');
    }

    /**
     * Ensure the user has the ManageRoles permission to manage members.
     */
    private function authorizeMemberAccess(Request $request): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $request->user()->hasPermission(PermissionFlag::ManageRoles),
            403,
            'You do not have permission to manage members.',
        );
    }
}
