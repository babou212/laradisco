<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MarkNotificationReadRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's unread notifications (cursor-paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadCount = $user->unreadNotifications()->count();

        $notifications = $user
            ->unreadNotifications()
            ->latest()
            ->cursorPaginate(50);

        return $this->successResponse([
            'notifications' => NotificationResource::collection($notifications->items()),
            'unread_count' => $unreadCount,
            'pagination' => [
                'next_cursor' => $notifications->nextCursor()?->encode(),
                'prev_cursor' => $notifications->previousCursor()?->encode(),
                'has_more' => $notifications->hasMorePages(),
            ],
        ]);
    }

    /**
     * Mark a specific notification as read.
     *
     * PATCH /notifications/{notification} with { read: true }
     */
    public function markAsRead(MarkNotificationReadRequest $request, string $notification): JsonResponse
    {
        $notificationModel = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $notificationModel->markAsRead();

        return $this->successResponse([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 'Notification marked as read');
    }

    /**
     * Mark all notifications as read.
     *
     * PATCH /notifications with { read: true }
     */
    public function markAllAsRead(MarkNotificationReadRequest $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse(
            ['unread_count' => 0],
            'All notifications marked as read',
        );
    }
}
