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

/**
 * @group Channels & Messages
 */
class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * List sidebar categories
     *
     * Return the sidebar data: categories with their accessible channels. Each
     * channel is decorated with a per-user `has_unread` flag.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: channels. Example: channels
     * @queryParam sort string Sort field; prefix - for descending. Allowed: position. Example: position
     *
     * @response 200 {"data": [{"type": "categories", "id": "3", "attributes": {"name": "Text Channels", "position": 0, "created_at": "2026-06-30T12:00:00.000000Z"}, "relationships": {"channels": {"data": [{"type": "channels", "id": "42"}]}}}], "included": [{"type": "channels", "id": "42", "attributes": {"name": "general", "channel_type": "text", "position": 0, "has_unread": false}}]}
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
     * List server members
     *
     * Get server members, cursor-paginated, with optional username search.
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: roles. Example: roles
     * @queryParam sort string Sort field; prefix - for descending. Allowed: username. Example: username
     * @queryParam filter[search] string Partial, case-insensitive match on username. Example: ali
     *
     * @response 200 {"data": [{"type": "users", "id": "7", "attributes": {"username": "alice", "display_name": "Alice", "avatar_urls": null, "status": "online", "custom_status": null, "created_at": "2026-06-30T12:00:00.000000Z"}}], "links": {"first": null, "last": null, "prev": null, "next": null}, "meta": {"path": "...", "per_page": 50, "next_cursor": null, "prev_cursor": null}}
     */
    public function members(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::partial('search', 'username'),
            )
            ->allowedIncludes('roles')
            ->allowedSorts('username')
            ->defaultSort('username')
            ->select(['id', 'username', 'status', 'custom_status', 'created_at'])
            ->with(['roles' => fn ($q) => $q->orderByDesc('position')])
            ->cursorPaginate(50);

        return UserSummaryResource::collection($users)
            ->includePreviouslyLoadedRelationships()
            ->response();
    }
}
