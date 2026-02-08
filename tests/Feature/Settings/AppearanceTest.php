<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_appearance_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('appearance.edit'));

        $response->assertOk();
    }

    public function test_appearance_page_returns_current_theme(): void
    {
        $user = User::factory()->create(['theme' => 'nord']);

        $response = $this
            ->actingAs($user)
            ->get(route('appearance.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Appearance')
            ->has('currentTheme')
            ->where('currentTheme', 'nord')
        );
    }

    public function test_theme_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('appearance.update'), [
                'theme' => 'dracula',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appearance.edit'));

        $this->assertSame('dracula', $user->refresh()->theme);
    }

    public function test_theme_defaults_to_default(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('appearance.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currentTheme', 'default')
        );
    }

    public function test_all_valid_themes_can_be_selected(): void
    {
        $user = User::factory()->create();

        $validThemes = ['default', 'default-dark', 'nord', 'rose', 'ocean', 'forest', 'sunset', 'lavender', 'solarized-light', 'dracula', 'nord-dark', 'midnight', 'cyberpunk', 'monokai', 'emerald', 'solarized-dark', 'crimson'];

        foreach ($validThemes as $theme) {
            $response = $this
                ->actingAs($user)
                ->patch(route('appearance.update'), [
                    'theme' => $theme,
                ]);

            $response->assertSessionHasNoErrors();
            $this->assertSame($theme, $user->refresh()->theme);
        }
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('appearance.update'), [
                'theme' => 'nonexistent-theme',
            ]);

        $response->assertSessionHasErrors('theme');
    }

    public function test_empty_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('appearance.update'), [
                'theme' => '',
            ]);

        $response->assertSessionHasErrors('theme');
    }

    public function test_guests_cannot_access_appearance_page(): void
    {
        $response = $this->get(route('appearance.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_update_theme(): void
    {
        $response = $this->patch(route('appearance.update'), [
            'theme' => 'nord',
        ]);

        $response->assertRedirect(route('login'));
    }
}
