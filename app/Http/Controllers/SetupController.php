<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    use PasswordValidationRules;

    /**
     * Show the initial setup page.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('auth/Setup', [
            'user' => $request->user()->only('name', 'username', 'email'),
        ]);
    }

    /**
     * Process the initial setup form.
     */
    public function complete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => $this->passwordRules(),
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'must_setup' => false,
        ]);

        return redirect()->route('chat');
    }
}
