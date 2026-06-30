<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Events\UserRolesUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\ModerationAuditService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Settings: Members
 */
class MemberController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ModerationAuditService $auditService,
    ) {}

    /**
     * List all members with their roles.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: roles. Example: roles
     * @queryParam sort string Sort field; prefix - for descending. Allowed: username, created_at. Example: username
     * @queryParam filter[username] string Partial username match. Example: ali
     *
     * @response 200 {"data":[{"type":"users","id":"7","attributes":{"username":"alice","display_name":"Alice","avatar_urls":null,"status":"online","custom_status":null,"created_at":"2026-06-30T12:00:00.000000Z"}}],"meta":{"roles":[{"type":"roles","id":"1","attributes":{"name":"Admin","color":"#ff5555","position":5,"is_default":false}}]}}
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-members');

        $members = QueryBuilder::for(
            User::select(['id', 'username', 'status', 'custom_status', 'created_at'])
        )
            ->allowedIncludes('roles')
            ->allowedSorts('username', 'created_at')
            ->allowedFilters(
                AllowedFilter::partial('username'),
            )
            ->defaultSort('username')
            ->with(['roles' => fn ($q) => $q->orderByDesc('position')])
            ->paginate(50);

        $roles = Role::query()
            ->orderByDesc('position')
            ->get(['id', 'name', 'color', 'position', 'is_default']);

        return UserResource::collection($members)
            ->includePreviouslyLoadedRelationships()
            ->additional([
                'meta' => [
                    'roles' => RoleResource::collection($roles),
                ],
            ])
            ->response();
    }

    /**
     * Assign a role to a member.
     *
     * @response 200 {"message":"Role assigned successfully"}
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        Gate::authorize('manage-members');

        /** @var Role $role */
        $role = Role::query()->findOrFail($request->validated('role_id'));
        $user->assignRole($role);

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::RoleAssign,
            targetUserId: $user->id,
            metadata: [
                'target_username' => $user->username,
                'role_name' => $role->name,
                'role_id' => $role->id,
            ],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));
        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        UserRolesUpdated::dispatch($user->fresh()->load('roles'));

        return $this->successResponse(message: 'Role assigned successfully');
    }

    /**
     * Remove a role from a member.
     *
     * @response 204
     */
    public function removeRole(Request $request, User $user, Role $role): JsonResponse|Response
    {
        Gate::authorize('manage-members');

        if ($role->is_default) {
            return $this->forbiddenResponse('Cannot remove the default role.');
        }

        $user->removeRole($role);

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::RoleRemove,
            targetUserId: $user->id,
            metadata: [
                'target_username' => $user->username,
                'role_name' => $role->name,
                'role_id' => $role->id,
            ],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));
        Cache::tags([CacheKeys::TAG_ROLES])->flush();

        UserRolesUpdated::dispatch($user->fresh()->load('roles'));

        return $this->noContentResponse();
    }
}
