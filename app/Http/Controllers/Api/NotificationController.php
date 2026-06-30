<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Notifications
 */
class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * List unread notifications
     *
     * Get the authenticated user's unread notifications (cursor-paginated).
     * `meta.unread_count` carries the total unread count.
     *
     * @queryParam sort string Sort field; prefix - for descending. Defaults to -created_at. Allowed: created_at. Example: -created_at
     *
     * @responseField data object[] The unread notifications, newest first.
     * @responseField meta.unread_count integer Total number of unread notifications.
     *
     * @response 200 {"data": [{"type": "notifications", "id": "9b1f...", "attributes": {"type": "DirectMessageNotification", "data": {"message_id": 560}, "read_at": null, "created_at": "2026-06-30T12:05:00.000000Z"}}], "meta": {"unread_count": 1}}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $countCacheKey = CacheKeys::userUnreadNotificationCount($user->id);

        $unreadCount = Cache::tags([CacheKeys::userTag($user->id)])
            ->remember($countCacheKey, CacheKeys::TTL_HOT, fn () => $user->unreadNotifications()->count());

        $notifications = QueryBuilder::for(
            $user->unreadNotifications()
        )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(50);

        return NotificationResource::collection($notifications)
            ->additional([
                'meta' => [
                    'unread_count' => $unreadCount,
                ],
            ])
            ->response();
    }

    /**
     * Mark a notification as read
     *
     * Mark a specific notification as read. Returns the updated unread count.
     *
     * @response 200 {"meta": {"unread_count": 0}}
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $notificationModel = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $notificationModel->markAsRead();

        $user = $request->user();
        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userUnreadNotificationCount($user->id));

        return response()->json([
            'meta' => [
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * Mark all of the authenticated user's notifications as read.
     *
     * @response 200 {"meta": {"unread_count": 0}}
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        Cache::tags([CacheKeys::userTag($request->user()->id)])->forget(CacheKeys::userUnreadNotificationCount($request->user()->id));

        return response()->json([
            'meta' => [
                'unread_count' => 0,
            ],
        ]);
    }
}
