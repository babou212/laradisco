<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\InviteLink;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invite' => ['required', 'string'],
        ])->validate();

        $invite = InviteLink::where('token', $input['invite'])->first();

        if (! $invite || ! $invite->isValid()) {
            throw ValidationException::withMessages([
                'invite' => ['This invite link is invalid or has expired.'],
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
            'notification_preferences' => [
                'enable_toast_notifications' => true,
                'enable_browser_notifications' => true,
                'enable_dm_notifications' => true,
                'enable_mention_notifications' => true,
            ],
        ]);

        // Mark the invite as used...
        $invite->update([
            'used_at' => now(),
            'used_by' => $user->id,
        ]);

        // Assign the default (@everyone) role...
        $defaultRole = Role::where('is_default', true)->first();

        if ($defaultRole) {
            $user->roles()->attach($defaultRole->id);
        }

        return $user;
    }
}
