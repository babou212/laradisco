<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the notification settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Notifications', [
            'preferences' => $request->user()->notification_preferences ?? $this->defaults(),
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enable_toast_notifications' => ['required', 'boolean'],
            'enable_browser_notifications' => ['required', 'boolean'],
            'enable_dm_notifications' => ['required', 'boolean'],
            'enable_mention_notifications' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => $validated,
        ]);

        return to_route('notifications.edit');
    }

    /**
     * Get the default notification preferences.
     *
     * @return array<string, bool>
     */
    private function defaults(): array
    {
        return [
            'enable_toast_notifications' => true,
            'enable_browser_notifications' => true,
            'enable_dm_notifications' => true,
            'enable_mention_notifications' => true,
        ];
    }
}
