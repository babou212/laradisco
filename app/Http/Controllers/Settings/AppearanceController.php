<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the appearance settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Appearance', [
            'currentTheme' => $request->user()->theme ?? 'default',
        ]);
    }

    /**
     * Update the user's theme preference.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return to_route('appearance.edit');
    }
}
