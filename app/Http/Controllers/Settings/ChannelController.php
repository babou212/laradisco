<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PermissionFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCategoryRequest;
use App\Http\Requests\Settings\StoreChannelRequest;
use App\Http\Requests\Settings\UpdateCategoryRequest;
use App\Http\Requests\Settings\UpdateChannelRequest;
use App\Models\Category;
use App\Models\Channel;
use App\Models\ChannelPermissionOverride;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Display the channel management page.
     */
    public function index(Request $request): Response
    {
        $this->authorizeChannelAccess($request);

        $categories = Category::with(['channels' => function ($query) {
            $query->orderBy('position');
        }])
            ->orderBy('position')
            ->get();

        $roles = Role::orderByDesc('position')->get(['id', 'name', 'color']);

        $permissions = collect(PermissionFlag::cases())->map(fn (PermissionFlag $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ]);

        return Inertia::render('settings/Channels', [
            'categories' => $categories,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a new channel.
     */
    public function storeChannel(StoreChannelRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Channel::create($validated);

        return redirect()->route('settings.channels.index');
    }

    /**
     * Update the specified channel.
     */
    public function updateChannel(UpdateChannelRequest $request, Channel $channel): RedirectResponse
    {
        $channel->update($request->validated());

        return redirect()->route('settings.channels.index');
    }

    /**
     * Remove the specified channel.
     */
    public function destroyChannel(Request $request, Channel $channel): RedirectResponse
    {
        $this->authorizeChannelAccess($request);

        $channel->delete();

        return redirect()->route('settings.channels.index');
    }

    /**
     * Store a new category.
     */
    public function storeCategory(StoreCategoryRequest $request): RedirectResponse
    {
        $maxPosition = Category::max('position') ?? -1;

        Category::create([
            'name' => $request->validated('name'),
            'position' => $maxPosition + 1,
        ]);

        return redirect()->route('settings.channels.index');
    }

    /**
     * Update the specified category.
     */
    public function updateCategory(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('settings.channels.index');
    }

    /**
     * Remove the specified category and its channels.
     */
    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeChannelAccess($request);

        $category->channels()->delete();
        $category->delete();

        return redirect()->route('settings.channels.index');
    }

    /**
     * Get channel permission overrides for a specific channel.
     */
    public function getOverrides(Request $request, Channel $channel): \Illuminate\Http\JsonResponse
    {
        $this->authorizeChannelAccess($request);

        $overrides = $channel->permissionOverrides()
            ->with(['role:id,name,color', 'user:id,username,name'])
            ->get();

        return response()->json($overrides);
    }

    /**
     * Store or update a channel permission override.
     */
    public function storeOverride(Request $request, Channel $channel): RedirectResponse
    {
        $this->authorizeChannelAccess($request);

        $validPermissions = implode(',', array_map(fn (PermissionFlag $p) => $p->value, PermissionFlag::cases()));

        $validated = $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'allow' => ['array'],
            'allow.*' => ['string', "in:{$validPermissions}"],
            'deny' => ['array'],
            'deny.*' => ['string', "in:{$validPermissions}"],
        ]);

        if (empty($validated['role_id']) && empty($validated['user_id'])) {
            abort(422, 'Either a role or user must be specified.');
        }

        ChannelPermissionOverride::updateOrCreate(
            [
                'channel_id' => $channel->id,
                'role_id' => $validated['role_id'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
            ],
            [
                'allow' => $validated['allow'] ?? [],
                'deny' => $validated['deny'] ?? [],
            ],
        );

        $this->permissionService->clearChannelCaches($channel);

        return redirect()->route('settings.channels.index');
    }

    /**
     * Remove a channel permission override.
     */
    public function destroyOverride(Request $request, Channel $channel, ChannelPermissionOverride $override): RedirectResponse
    {
        $this->authorizeChannelAccess($request);

        $override->delete();

        $this->permissionService->clearChannelCaches($channel);

        return redirect()->route('settings.channels.index');
    }

    /**
     * Ensure the user has the ManageChannels permission.
     */
    private function authorizeChannelAccess(Request $request): void
    {
        abort_unless(
            $request->user()->isAdministrator() || $request->user()->hasPermission(PermissionFlag::ManageChannels),
            403,
            'You do not have permission to manage channels.',
        );
    }
}
