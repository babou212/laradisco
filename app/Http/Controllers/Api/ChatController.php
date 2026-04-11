<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Category;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            return response()->json($this->decorateUnread($user->id, $cached));
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

        $payload = $response->getData(true);

        Cache::tags([CacheKeys::userTag($user->id), CacheKeys::TAG_SIDEBAR])
            ->put($fullCacheKey, $payload, CacheKeys::TTL_WARM);

        return response()->json($this->decorateUnread($user->id, $payload));
    }

    /**
     * Decorate the cached categories payload with per-channel has_unread for this user.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function decorateUnread(int $userId, array $payload): array
    {
        $channelIds = [];
        foreach ($payload['included'] ?? [] as $item) {
            if (($item['type'] ?? null) === 'channels' && isset($item['id'])) {
                $channelIds[] = (int) $item['id'];
            }
        }

        if (empty($channelIds)) {
            return $payload;
        }

        $latest = Message::query()
            ->selectRaw('channel_id, MAX(created_at) as latest_at')
            ->whereIn('channel_id', $channelIds)
            ->whereNull('thread_id')
            ->groupBy('channel_id')
            ->pluck('latest_at', 'channel_id')
            ->all();

        $reads = DB::table('channel_user')
            ->where('user_id', $userId)
            ->whereIn('channel_id', $channelIds)
            ->pluck('last_read_at', 'channel_id')
            ->all();

        foreach ($payload['included'] ?? [] as $idx => $item) {
            if (($item['type'] ?? null) !== 'channels') {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            $latestAt = $latest[$id] ?? null;
            $lastReadAt = $reads[$id] ?? null;

            if ($latestAt === null) {
                $hasUnread = false;
            } elseif ($lastReadAt === null) {
                $hasUnread = true;
            } else {
                $hasUnread = strtotime((string) $latestAt) > strtotime((string) $lastReadAt);
            }

            $payload['included'][$idx]['attributes']['has_unread'] = $hasUnread;
        }

        return $payload;
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
