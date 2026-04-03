<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
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

        $cacheKey = CacheKeys::rolesListWithCounts();
        $cached = Cache::tags([CacheKeys::TAG_ROLES])->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $roles = QueryBuilder::for(Role::class)
            ->allowedSorts('position', 'name')
            ->defaultSort('-position')
            ->withCount('users')
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

        $role = Role::create($request->validated());

        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing role.
     */
    public function update(StoreRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role->update($request->validated());

        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        return (new RoleResource($role->fresh()))
            ->response();
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, Role $role): JsonResponse|Response
    {
        $this->authorize('delete', $role);

        $role->users()->detach();
        $role->delete();

        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        return $this->noContentResponse();
    }
}
