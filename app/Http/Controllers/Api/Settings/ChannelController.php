<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChannelOverrideRequest;
use App\Http\Requests\Api\StoreChannelRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\RoleResource;
use App\Models\Category;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use App\Services\ModerationAuditService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ModerationAuditService $auditService,
    ) {}

    /**
     * List all channels grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Channel::class);

        $categories = QueryBuilder::for(Category::class)
            ->allowedIncludes('channels')
            ->allowedSorts('position')
            ->defaultSort('position')
            ->with(['channels' => fn ($q) => $q->orderBy('position')])
            ->get();

        $roles = Role::orderByDesc('position')->get(['id', 'name', 'color']);

        $permissions = collect(PermissionFlag::cases())->map(fn (PermissionFlag $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        return CategoryResource::collection($categories)
            ->includePreviouslyLoadedRelationships()
            ->additional([
                'meta' => [
                    'roles' => RoleResource::collection($roles),
                    'permissions' => $permissions,
                ],
            ])
            ->response();
    }

    /**
     * Create a new channel.
     */
    public function store(StoreChannelRequest $request): JsonResponse
    {
        $this->authorize('create', Channel::class);

        $channel = Channel::create($request->validated());

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::ChannelCreate,
            targetResourceId: $channel->id,
            targetResourceType: 'channel',
            metadata: ['channel_name' => $channel->name],
        );

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();

        return (new ChannelResource($channel))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('api.channels.show', $channel));
    }

    /**
     * Update a channel's settings.
     */
    public function update(StoreChannelRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        $channel->update($request->validated());

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::ChannelUpdate,
            targetResourceId: $channel->id,
            targetResourceType: 'channel',
            metadata: ['channel_name' => $channel->name],
        );

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();
        Cache::tags([CacheKeys::channelTag($channel->id)])->flush();

        return (new ChannelResource($channel->fresh()))
            ->response();
    }

    /**
     * Delete a channel.
     */
    public function destroy(Request $request, Channel $channel): JsonResponse|Response
    {
        $this->authorize('delete', $channel);

        $channelId = $channel->id;
        $channelName = $channel->name;
        $channel->delete();

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::ChannelDelete,
            targetResourceId: $channelId,
            targetResourceType: 'channel',
            metadata: ['channel_name' => $channelName],
        );

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();
        Cache::tags([CacheKeys::channelTag($channelId)])->flush();

        return $this->noContentResponse();
    }

    /**
     * List permission overrides for a channel.
     */
    public function overrides(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('manageOverrides', $channel);

        $overrides = $channel->permissionOverrides()
            ->with(['role:id,name,color', 'user:id,username'])
            ->get();

        return $this->successResponse($overrides);
    }

    /**
     * Create or update a permission override for a channel.
     */
    public function storeOverride(StoreChannelOverrideRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('manageOverrides', $channel);

        $validated = $request->validated();

        $override = ChannelPermissionOverride::updateOrCreate(
            [
                'channel_id' => $channel->id,
                'role_id' => $validated['role_id'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
            ],
            [
                'allow' => $validated['allow'] ?? [],
                'deny' => $validated['deny'] ?? [],
            ]
        );

        $override->load(['role:id,name,color', 'user:id,username']);

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::ChannelOverrideUpdate,
            targetResourceId: $channel->id,
            targetResourceType: 'channel',
            metadata: array_filter([
                'channel_name' => $channel->name,
                'role_name' => $override->role?->name,
                'user_username' => $override->user?->username,
            ]),
        );

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();
        Cache::tags([CacheKeys::channelTag($channel->id)])->flush();

        return $this->createdResponse($override);
    }

    /**
     * Delete a permission override.
     */
    public function destroyOverride(Request $request, Channel $channel, ChannelPermissionOverride $override): JsonResponse|Response
    {
        $this->authorize('manageOverrides', $channel);

        $this->auditService->log(
            actorId: $request->user()->id,
            action: ModerationAction::ChannelOverrideDelete,
            targetResourceId: $channel->id,
            targetResourceType: 'channel',
            metadata: ['channel_name' => $channel->name],
        );

        $override->delete();

        Cache::tags([CacheKeys::TAG_SIDEBAR])->flush();
        Cache::tags([CacheKeys::channelTag($channel->id)])->flush();

        return $this->noContentResponse();
    }
}
