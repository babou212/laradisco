<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\UserStatusType;
use App\Events\UserActivityUpdated;
use App\Events\UserPresenceUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePresenceActivityRequest;
use App\Http\Requests\Api\UpdatePresenceRequest;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Presence
 */
class PresenceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PresenceService $presenceService,
    ) {}

    /**
     * List online users
     *
     * Get all currently online users from the Redis-backed registry.
     *
     * @response 200 {"data": [{"id": 7, "username": "bob", "display_name": "Bob", "avatar_urls": null, "status": "online", "custom_status": null}]}
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->presenceService->getOnlineUsers(),
        );
    }

    /**
     * Update presence status
     *
     * Update the authenticated user's presence status. Sending `offline`
     * unregisters the user from the live registry.
     *
     * @response 200 {"message": "Presence updated successfully"}
     */
    public function update(UpdatePresenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $status = UserStatusType::from($request->validated('status'));

        $user->update([
            'status' => $status->value,
            'custom_status' => $request->validated('custom_status'),
        ]);

        // When going offline, unregister from the presence list instead of
        // updating status. Otherwise, update the Redis-backed registry.
        if ($status === UserStatusType::Offline) {
            $this->presenceService->unregister($user);
        } else {
            $this->presenceService->updateStatus(
                $user,
                $status->value,
                $request->validated('custom_status'),
            );
        }

        event(new UserPresenceUpdated(
            $user,
            $status,
            $request->validated('custom_status'),
        ));

        return $this->successResponse(message: 'Presence updated successfully');
    }

    /**
     * Update rich-presence activity
     *
     * Update the authenticated user's live rich-presence activity.
     *
     * The `started_at` timestamp is stamped server-side, so only the descriptive
     * fields are accepted here. A null activity clears it. The privacy flag is
     * enforced inside the service, which discards activity when sharing is off.
     *
     * @response 200 {"message": "Activity updated successfully"}
     */
    public function updateActivity(UpdatePresenceActivityRequest $request): JsonResponse
    {
        $user = $request->user();
        $input = $request->validated('activity');

        $activity = $input === null ? null : [
            'type' => $input['type'],
            'name' => $input['name'],
            'application_id' => $input['application_id'],
            'details' => $input['details'] ?? null,
            'icon' => $input['icon'] ?? null,
        ];

        $stored = $this->presenceService->updateActivity($user, $activity);

        event(new UserActivityUpdated($user, $stored));

        return $this->successResponse(message: 'Activity updated successfully');
    }

    /**
     * Record a presence heartbeat
     *
     * Refresh the authenticated user's heartbeat in the Redis registry. A
     * heartbeat may revive a user that a prior lapse downgraded to idle/offline.
     *
     * @response 200 {"message": "Heartbeat recorded"}
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();

        // A heartbeat may revive a user that a prior lapse downgraded to
        // idle/offline. When it does, broadcast the restored status so other
        // clients see them come back online immediately.
        $transition = $this->presenceService->heartbeat($user);

        if ($transition !== null) {
            event(new UserPresenceUpdated($user, $transition, $user->custom_status));
        }

        return $this->successResponse(message: 'Heartbeat recorded');
    }

    // offline behaviour is now handled by PATCH /presence with { status: "offline" }
    // See the update() method above.
}
