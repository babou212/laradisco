<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Category;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Return the sidebar data: categories with their accessible channels.
     */
    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = CacheKeys::userSidebarCategories($user->id);
        $includes = $request->query('include', '');
        $fullCacheKey = $cacheKey.'.'.md5($includes);

        $cached = Cache::tags([CacheKeys::userTag($user->id), CacheKeys::TAG_SIDEBAR])->get($fullCacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        $accessibleChannelIds = $this->permissionService
            ->getAccessibleChannels($user)
            ->pluck('id')
            ->all();

        $categories = QueryBuilder::for(Category::class)
            ->allowedIncludes('channels')
            ->allowedSorts('position')
            ->defaultSort('position')
            ->with(['channels' => function ($query) use ($accessibleChannelIds) {
                $query->whereIn('id', $accessibleChannelIds)
                    ->orderBy('position');
            }])
            ->get()
            ->filter(fn (Category $category) => $category->channels->isNotEmpty())
            ->values();

        $response = CategoryResource::collection($categories)
            ->response();

        Cache::tags([CacheKeys::userTag($user->id), CacheKeys::TAG_SIDEBAR])
            ->put($fullCacheKey, $response->getData(true), CacheKeys::TTL_WARM);

        return $response;
    }

    /**
     * Get server members (cursor-paginated, with optional search).
     */
    public function members(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(User::class)
            ->select(['id', 'name', 'username', 'nickname', 'status', 'custom_status'])
            ->allowedFilters(
                AllowedFilter::partial('search', 'username'),
                AllowedFilter::partial('search_name', 'name'),
                AllowedFilter::partial('search_nickname', 'nickname'),
            )
            ->allowedSorts('name', 'username')
            ->defaultSort('name')
            ->cursorPaginate(50);

        return UserSummaryResource::collection($users)
            ->response();
    }
}
