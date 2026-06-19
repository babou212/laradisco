<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePresenceRequest;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PresenceService $presenceService,
    ) {}

    /**
     * Get all currently online users from the Redis-backed registry.
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->presenceService->getOnlineUsers(),
        );
    }

    /**
     * Update the authenticated user's presence status.
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
     * Refresh the authenticated user's heartbeat in the Redis registry.
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
