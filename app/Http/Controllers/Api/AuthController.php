<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\UserStatusType;
use App\Events\ServerMemberJoined;
use App\Events\UserPresenceUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Models\InviteLink;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PresenceService $presenceService,
        private readonly TwoFactorAuthenticationProvider $twoFactorProvider,
    ) {}

    /**
     * Issue a new API token for the native client.
     *
     * If the user has two-factor authentication enabled, a challenge token
     * is returned instead of an API token, requiring a second step.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        $driver = str_starts_with((string) $user?->password, '$2y$') ? 'bcrypt' : null;

        if (! $user || ! Hash::driver($driver)->check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($request->password)])->save();
        }

        if ($user->isBanned()) {
            return $this->bannedResponse($user);
        }

        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $challengeToken = Str::random(64);

            Cache::put(
                "two_factor_challenge:{$challengeToken}",
                ['user_id' => $user->id, 'device_name' => $request->device_name],
                now()->addMinutes(5),
            );

            return $this->successResponse([
                'two_factor' => true,
                'challenge_token' => $challengeToken,
            ], 'Two-factor authentication required');
        }

        return $this->issueToken($user, $request->device_name);
    }

    /**
     * Verify a two-factor authentication code and issue an API token.
     */
    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $challenge = Cache::pull("two_factor_challenge:{$request->validated('challenge_token')}");

        if (! $challenge) {
            throw ValidationException::withMessages([
                'code' => ['The two-factor challenge has expired. Please log in again.'],
            ]);
        }

        /** @var User $user */
        $user = User::findOrFail($challenge['user_id']);

        if ($user->isBanned()) {
            return $this->bannedResponse($user);
        }

        if ($request->filled('code')) {
            $valid = $this->twoFactorProvider->verify(
                decrypt($user->two_factor_secret),
                $request->code,
            );

            if (! $valid) {
                throw ValidationException::withMessages([
                    'code' => ['The provided two-factor code is invalid.'],
                ]);
            }
        } elseif ($request->filled('recovery_code')) {
            /** @var list<string> $codes */
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $matchIndex = collect($codes)->search(fn (string $c) => hash_equals($c, $request->recovery_code));

            if ($matchIndex === false) {
                throw ValidationException::withMessages([
                    'recovery_code' => ['The provided recovery code is invalid.'],
                ]);
            }

            unset($codes[$matchIndex]);
            $user->forceFill([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
            ])->save();
        } else {
            throw ValidationException::withMessages([
                'code' => ['A two-factor code or recovery code is required.'],
            ]);
        }

        return $this->issueToken($user, $challenge['device_name']);
    }

    /**
     * Issue a Sanctum token and set the user online.
     */
    private function issueToken(User $user, string $deviceName): JsonResponse
    {
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        $user->update(['status' => UserStatusType::Online->value]);
        $this->presenceService->register($user);
        event(new UserPresenceUpdated($user, UserStatusType::Online, $user->custom_status));

        $user->load(['roles' => fn ($query) => $query->orderByDesc('position')]);

        request()->setUserResolver(fn () => $user);

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Authenticated successfully');
    }

    /**
     * Build the 403 response returned when a banned user tries to authenticate.
     *
     * Uses a plain JSON body (not the JSON:API envelope) with stable top-level
     * fields — the same shape as the CheckBanned middleware — so the client can
     * render the ban reason and remaining duration on its banned screen.
     */
    private function bannedResponse(User $user): JsonResponse
    {
        $ban = $user->activeBan();

        return response()->json([
            'code' => 'account_banned',
            'message' => 'Your account has been banned.',
            'reason' => $ban?->reason,
            'expires_at' => $ban?->expires_at?->format(DATE_ATOM),
            'permanent' => $ban?->expires_at === null,
        ], 403);
    }

    /**
     * Revoke the current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->presenceService->unregister($user);
        event(new UserPresenceUpdated($user, UserStatusType::Offline));

        $user->currentAccessToken()->delete();

        return $this->successResponse(message: 'Logged out successfully');
    }

    /**
     * Return the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles' => fn ($query) => $query->orderByDesc('position')]);

        return $this->successResponse(new UserResource($user));
    }

    /**
     * Validate an invite token without consuming it.
     */
    public function validateInvite(string $token): JsonResponse
    {
        $invite = InviteLink::where('token', $token)->first();

        if (! $invite || ! $invite->isValid()) {
            return $this->errorResponse('This invite link is invalid or has expired.', 404);
        }

        return $this->successResponse([
            'valid' => true,
            'expires_at' => $invite->expires_at->toISOString(),
        ]);
    }

    /**
     * Register a new user using a valid invite token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $invite = InviteLink::where('token', $request->validated('invite_token'))->first();

        if (! $invite || ! $invite->isValid()) {
            throw ValidationException::withMessages([
                'invite_token' => ['This invite link is invalid or has expired.'],
            ]);
        }

        $user = DB::transaction(function () use ($request, $invite) {
            $claimed = InviteLink::where('id', $invite->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update([
                    'used_at' => now(),
                ]);

            if (! $claimed) {
                throw ValidationException::withMessages([
                    'invite_token' => ['This invite link is invalid or has expired.'],
                ]);
            }

            $user = User::create([
                'username' => $request->validated('username'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ]);

            $invite->update(['used_by' => $user->id]);

            $everyoneRole = Role::where('name', 'everyone')->first();
            if ($everyoneRole) {
                $user->assignRole($everyoneRole);
            }

            return $user;
        });

        event(new ServerMemberJoined($user));

        return $this->issueToken($user, $request->validated('device_name'));
    }

    /**
     * Send a password reset code to the user's email.
     *
     * Generates a 6-digit code stored in cache for 15 minutes.
     * Does NOT modify the user's password.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            Cache::put(
                "password_reset:{$user->id}",
                ['code' => Hash::make($code), 'email' => $user->email],
                now()->addMinutes(15),
            );

            $user->notify(new PasswordResetCodeNotification($code));
        }

        return $this->successResponse(
            message: 'If an account with that email exists, a reset code has been sent.',
        );
    }

    /**
     * Reset the user's password using a valid reset code.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => ['The reset code is invalid or has expired.'],
            ]);
        }

        $cached = Cache::get("password_reset:{$user->id}");

        if (! $cached || ! Hash::check($request->validated('code'), $cached['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The reset code is invalid or has expired.'],
            ]);
        }

        Cache::forget("password_reset:{$user->id}");

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        return $this->successResponse(
            message: 'Your password has been reset successfully. You can now sign in.',
        );
    }
}
