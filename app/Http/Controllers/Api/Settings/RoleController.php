<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    use ApiResponse;

    /**
     * List all roles with user counts.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount('users')
            ->orderByDesc('position')
            ->get();

        $permissions = collect(PermissionFlag::cases())->map(fn (PermissionFlag $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        return $this->successResponse([
            'roles' => RoleResource::collection($roles),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create($request->validated());

        return $this->createdResponse(new RoleResource($role));
    }

    /**
     * Update an existing role.
     */
    public function update(StoreRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role->update($request->validated());

        return $this->successResponse(
            new RoleResource($role->fresh()),
            'Role updated successfully',
        );
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, Role $role): JsonResponse|Response
    {
        $this->authorize('delete', $role);

        $role->users()->detach();
        $role->delete();

        return $this->noContentResponse();
    }
}
