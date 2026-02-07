<?php

namespace App\Http\Controllers;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
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

        // Broadcast presence update to all connected users
        broadcast(new UserPresenceUpdated(
            $user,
            $status,
            $validated['custom_status'] ?? null
        ));

        return back();
    }
}
