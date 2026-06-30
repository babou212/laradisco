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
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Settings: Roles
 */
class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ModerationAuditService $auditService,
    ) {}

    /**
     * List all roles with user counts.
     *
     * @queryParam sort string Sort field; prefix - for descending. Allowed: position, name. Example: -position
     *
     * @response 200 {"data":[{"type":"roles","id":"1","attributes":{"name":"Admin","color":"#ff5555","position":5,"is_hoisted":true,"is_default":false,"is_mentionable":true,"permissions":["manage_server"],"users_count":3,"created_at":"2026-06-30T12:00:00.000000Z"}}],"meta":{"permissions":[{"value":"manage_server","label":"Manage Server"}]}}
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
     *
     * @response 201 {"data":{"type":"roles","id":"6","attributes":{"name":"Moderator","color":"#55ff55","position":2,"is_hoisted":false,"is_default":false,"is_mentionable":true,"permissions":["kick_members"],"created_at":"2026-06-30T12:00:00.000000Z"}}}
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
     *
     * @response 200 {"data":{"type":"roles","id":"6","attributes":{"name":"Moderator","color":"#55ff55","position":2,"is_hoisted":false,"is_default":false,"is_mentionable":true,"permissions":["kick_members","ban_members"],"created_at":"2026-06-30T12:00:00.000000Z"}}}
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
     *
     * @response 204
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
