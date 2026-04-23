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

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's unread notifications (cursor-paginated).
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
     * Mark a specific notification as read.
     *
     * PATCH /notifications/{notification} with { read: true }
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
     * Mark all notifications as read.
     *
     * PATCH /notifications with { read: true }
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
