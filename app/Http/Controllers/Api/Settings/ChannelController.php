<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChannelController extends Controller
{
    use ApiResponse;

    /**
     * List all channels grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Channel::class);

        $categories = Category::with(['channels' => fn ($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get();

        $roles = Role::orderByDesc('position')->get(['id', 'name', 'color']);

        $permissions = collect(PermissionFlag::cases())->map(fn (PermissionFlag $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        return $this->successResponse([
            'categories' => CategoryResource::collection($categories),
            'roles' => RoleResource::collection($roles),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create a new channel.
     */
    public function store(StoreChannelRequest $request): JsonResponse
    {
        $this->authorize('create', Channel::class);

        $channel = Channel::create($request->validated());

        return $this->createdResponse(
            new ChannelResource($channel),
            'Created successfully',
            route('api.channels.show', $channel),
        );
    }

    /**
     * Update a channel's settings.
     */
    public function update(StoreChannelRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        $channel->update($request->validated());

        return $this->successResponse(
            new ChannelResource($channel->fresh()),
            'Channel updated successfully',
        );
    }

    /**
     * Delete a channel.
     */
    public function destroy(Request $request, Channel $channel): JsonResponse|Response
    {
        $this->authorize('delete', $channel);

        $channel->delete();

        return $this->noContentResponse();
    }

    /**
     * List permission overrides for a channel.
     */
    public function overrides(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('manageOverrides', $channel);

        $overrides = $channel->permissionOverrides()
            ->with(['role:id,name,color', 'user:id,username,name'])
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

        $override->load(['role:id,name,color', 'user:id,username,name']);

        return $this->createdResponse($override);
    }

    /**
     * Delete a permission override.
     */
    public function destroyOverride(Request $request, Channel $channel, ChannelPermissionOverride $override): JsonResponse|Response
    {
        $this->authorize('manageOverrides', $channel);

        $override->delete();

        return $this->noContentResponse();
    }
}
