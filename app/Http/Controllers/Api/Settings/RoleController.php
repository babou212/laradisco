<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\ModerationAuditService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ModerationAuditService $auditService,
    ) {}

    /**
     * List all roles with user counts.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $cacheKey = CacheKeys::rolesListWithCounts();
        $cached = Cache::tags([CacheKeys::TAG_ROLES])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $roles = QueryBuilder::for(Role::class)
            ->allowedSorts('position', 'name')
            ->defaultSort('-position')
            ->withCount('users')
            ->with('permissions')
            ->get();

        $permissions = collect(PermissionFlag::cases())->map(fn (PermissionFlag $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        $response = RoleResource::collection($roles)
            ->additional([
                'meta' => [
                    'permissions' => $permissions,
                ],
            ])
            ->response();

        Cache::tags([CacheKeys::TAG_ROLES])
            ->put($cacheKey, $response->getData(true), CacheKeys::TTL_COLD);

        return $response;
    }

    /**
     * Create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validated();
        $permissionNames = $validated['permissions'] ?? [];
        unset($validated['permissions']);

        $role = Role::create([
            ...$validated,
            'guard_name' => 'web',
        ]);

        if (! empty($permissionNames)) {
            $role->syncPermissions($permissionNames);
        }

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::RoleCreate,
            targetResourceId: $role->id,
            targetResourceType: 'role',
            metadata: ['role_name' => $role->name],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        return (new RoleResource($role->load('permissions')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing role.
     */
    public function update(StoreRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validated();
        $permissionNames = $validated['permissions'] ?? [];
        unset($validated['permissions']);

        $role->update($validated);

        $role->syncPermissions($permissionNames);

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::RoleUpdate,
            targetResourceId: $role->id,
            targetResourceType: 'role',
            metadata: ['role_name' => $role->name],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        return (new RoleResource($role->fresh()->load('permissions')))
            ->response();
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, Role $role): JsonResponse|Response
    {
        $this->authorize('delete', $role);

        $roleName = $role->name;
        $roleId = $role->id;

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::RoleDelete,
            targetResourceId: $roleId,
            targetResourceType: 'role',
            metadata: ['role_name' => $roleName],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        return $this->noContentResponse();
    }
}
