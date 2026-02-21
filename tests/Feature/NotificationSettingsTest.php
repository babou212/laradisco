<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Notifications')
            ->has('preferences')
        );
    }

    public function test_notification_settings_page_returns_default_preferences_when_none_set(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => null,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertInertia(fn ($page) => $page
            ->where('preferences.enable_toast_notifications', true)
            ->where('preferences.enable_browser_notifications', true)
            ->where('preferences.enable_dm_notifications', true)
            ->where('preferences.enable_mention_notifications', true)
        );
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('notifications.update'), [
            'enable_toast_notifications' => false,
            'enable_browser_notifications' => true,
            'enable_dm_notifications' => false,
            'enable_mention_notifications' => true,
        ]);

        $response->assertRedirect(route('notifications.edit'));

        $user->refresh();
        $this->assertFalse($user->notification_preferences['enable_toast_notifications']);
        $this->assertTrue($user->notification_preferences['enable_browser_notifications']);
        $this->assertFalse($user->notification_preferences['enable_dm_notifications']);
        $this->assertTrue($user->notification_preferences['enable_mention_notifications']);
    }

    public function test_notification_settings_page_returns_saved_preferences(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'enable_toast_notifications' => false,
                'enable_browser_notifications' => false,
                'enable_dm_notifications' => true,
                'enable_mention_notifications' => false,
            ],
        ]);

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertInertia(fn ($page) => $page
            ->where('preferences.enable_toast_notifications', false)
            ->where('preferences.enable_browser_notifications', false)
            ->where('preferences.enable_dm_notifications', true)
            ->where('preferences.enable_mention_notifications', false)
        );
    }

    public function test_notification_settings_require_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(route('notifications.update'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'enable_toast_notifications',
            'enable_browser_notifications',
            'enable_dm_notifications',
            'enable_mention_notifications',
        ]);
    }

    public function test_notification_settings_require_boolean_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(route('notifications.update'), [
            'enable_toast_notifications' => 'not-a-boolean',
            'enable_browser_notifications' => 123,
            'enable_dm_notifications' => 'yes',
            'enable_mention_notifications' => null,
        ]);

        $response->assertUnprocessable();
    }

    public function test_notification_settings_requires_authentication(): void
    {
        $response = $this->get(route('notifications.edit'));

        $response->assertRedirect(route('login'));
    }
}
