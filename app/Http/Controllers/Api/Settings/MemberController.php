<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    use ApiResponse;

    /**
     * List all members with their roles.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-members');

        $members = User::query()
            ->select(['id', 'name', 'username', 'nickname', 'status', 'custom_status', 'created_at'])
            ->with(['roles' => fn ($q) => $q->orderByDesc('position')])
            ->orderBy('username')
            ->paginate(50);

        $roles = Role::query()
            ->orderByDesc('position')
            ->get(['id', 'name', 'color', 'position', 'is_default']);

        return $this->successResponse([
            'members' => UserResource::collection($members),
            'roles' => RoleResource::collection($roles),
            'pagination' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    /**
     * Assign a role to a member.
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        Gate::authorize('manage-members');

        $user->roles()->syncWithoutDetaching([$request->validated('role_id')]);

        return $this->successResponse(message: 'Role assigned successfully');
    }

    /**
     * Remove a role from a member.
     *
     * DELETE /members/{user}/roles/{role}
     */
    public function removeRole(Request $request, User $user, Role $role): JsonResponse|Response
    {
        Gate::authorize('manage-members');

        if ($role->is_default) {
            return $this->forbiddenResponse('Cannot remove the default role.');
        }

        $user->roles()->detach($role->id);

        return $this->noContentResponse();
    }
}
