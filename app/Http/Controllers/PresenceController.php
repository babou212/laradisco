<?php

namespace App\Http\Controllers;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
    public function __construct(
        private PresenceService $presenceService,
    ) {}

    /**
     * Get all currently online users from the Redis-backed registry.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->presenceService->getOnlineUsers());
    }

    /**
     * Update the authenticated user's presence status.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatusType::class)],
            'custom_status' => ['nullable', 'string', 'max:128'],
        ]);

        $user = $request->user();
        $status = UserStatusType::from($validated['status']);

        // Update both status and custom_status in database
        $user->update([
            'status' => $status->value,
            'custom_status' => $validated['custom_status'] ?? null,
        ]);

        // Update Redis presence registry
        $this->presenceService->updateStatus(
            $user,
            $status->value,
            $validated['custom_status'] ?? null,
        );

        // Broadcast presence update to all connected users
        event(new UserPresenceUpdated(
            $user,
            $status,
            $validated['custom_status'] ?? null
        ));

        return back();
    }

    /**
     * Refresh the authenticated user's heartbeat in the Redis registry.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $this->presenceService->heartbeat($request->user());

        return response()->json(['ok' => true]);
    }
}
