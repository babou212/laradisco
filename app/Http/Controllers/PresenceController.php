<?php

namespace App\Http\Controllers;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
    /**
     * Update the authenticated user's presence status.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatusType::class)],
            'custom_status' => ['nullable', 'string', 'max:128'],
        ]);

        $user = $request->user();
        $status = UserStatusType::from($validated['status']);

        // Only update custom_status in database - status is managed by WebSocket presence
        $user->update([
            'custom_status' => $validated['custom_status'] ?? null,
        ]);

        // Broadcast presence update to all connected users
        broadcast(new UserPresenceUpdated(
            $user,
            $status,
            $validated['custom_status'] ?? null
        ));

        return response()->json([
            'status' => $status->value,
            'custom_status' => $user->custom_status,
        ]);
    }
}
