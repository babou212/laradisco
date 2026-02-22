<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Display the roles management page.
     */
    public function index(Request $request): Response
    {
        $this->authorizeRoleAccess($request);

        $roles = Role::query()
            ->withCount('users')
            ->orderByDesc('position')
            ->get();

        $permissions = collect(PermissionFlag::cases())->map(fn (PermissionFlag $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        return Inertia::render('settings/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::create($request->validated());

        return redirect()->route('settings.roles.index');
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        return redirect()->route('settings.roles.index');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAccess($request);

        if ($role->is_default) {
            abort(403, 'Cannot delete the default role.');
        }

        if (! $this->permissionService->canManageRole($request->user(), $role)) {
            abort(403, 'You cannot manage a role at or above your own position.');
        }

        $role->users()->detach();
        $role->delete();

        return redirect()->route('settings.roles.index');
    }

    /**
     * Ensure the user has the ManageRoles permission.
     */
    private function authorizeRoleAccess(Request $request): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $request->user()->hasPermission(PermissionFlag::ManageRoles),
            403,
            'You do not have permission to manage roles.',
        );
    }
}
