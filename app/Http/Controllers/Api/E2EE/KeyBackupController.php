<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\StoreKeyBackupRequest;
use App\Http\Requests\Api\E2EE\UnlockKeyBackupRequest;
use App\Models\UserKeyBackup;
use App\Services\KeyBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Symfony\Component\HttpFoundation\Response;

class KeyBackupController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly KeyBackupService $keyBackupService,
        private readonly TwoFactorAuthenticationProvider $twoFactorProvider,
    ) {}

    /**
     * Check if a key backup exists for the authenticated user.
     */
    public function exists(Request $request): JsonResponse
    {
        $exists = UserKeyBackup::where('user_id', $request->user()->id)->exists();

        return $this->successResponse([
            'exists' => $exists,
        ]);
    }

    /**
     * Store an encrypted key backup.
     */
    public function store(StoreKeyBackupRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (UserKeyBackup::where('user_id', $user->id)->exists()) {
            return $this->errorResponse(
                'Key backup already exists. Use PUT to update.',
                Response::HTTP_CONFLICT,
            );
        }

        UserKeyBackup::create([
            'user_id' => $user->id,
            'encrypted_bundle' => $validated['encrypted_bundle'],
            'salt' => $validated['salt'],
            'nonce' => $validated['nonce'],
            'argon2_params' => $validated['argon2_params'],
            'version' => 1,
        ]);

        return $this->createdResponse(null, 'Key backup stored successfully.');
    }

    /**
     * Retrieve the encrypted key backup.
     * Blocked while the backup is locked from too many failed restores.
     */
    public function show(Request $request): JsonResponse
    {
        $backup = UserKeyBackup::where('user_id', $request->user()->id)->first();

        if (! $backup) {
            return $this->notFoundResponse('No key backup found.');
        }

        $lockStatus = $this->keyBackupService->getLockStatus($backup);
        if ($lockStatus) {
            return $this->errorResponse(
                'Backup access is temporarily locked due to too many failed attempts. Try again later or unlock via 2FA.',
                Response::HTTP_LOCKED,
                $lockStatus,
            );
        }

        return $this->successResponse([
            'encrypted_bundle' => $backup->encrypted_bundle,
            'salt' => $backup->salt,
            'nonce' => $backup->nonce,
            'argon2_params' => $backup->argon2_params,
            'version' => $backup->version,
        ]);
    }

    /**
     * Record a client-reported failed decrypt. The server cannot observe
     * decryption, so the client reports failures explicitly; the backup
     * locks after too many.
     */
    public function reportFailure(Request $request): JsonResponse
    {
        $backup = UserKeyBackup::where('user_id', $request->user()->id)->first();

        if (! $backup) {
            return $this->notFoundResponse('No key backup found.');
        }

        $lockStatus = $this->keyBackupService->recordAttempt($backup);
        if ($lockStatus) {
            return $this->errorResponse(
                'Too many failed attempts. Backup access has been locked.',
                Response::HTTP_LOCKED,
                $lockStatus,
            );
        }

        return $this->successResponse([
            'remaining_attempts' => UserKeyBackup::MAX_FAILED_ATTEMPTS - $backup->failed_attempt_count,
        ], 'Failure recorded.');
    }

    /**
     * Confirm successful restore — resets the failed attempt counter.
     */
    public function confirmRestore(Request $request): JsonResponse
    {
        $backup = UserKeyBackup::where('user_id', $request->user()->id)->first();

        if (! $backup) {
            return $this->notFoundResponse('No key backup found.');
        }

        $this->keyBackupService->resetAttempts($backup);

        return $this->successResponse(null, 'Backup access confirmed.');
    }

    /**
     * Unlock a locked backup via 2FA verification.
     */
    public function unlock(UnlockKeyBackupRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->verifyTwoFactorCode($user, $request->input('two_factor_code'))) {
            return $this->errorResponse(
                'Invalid two-factor authentication code.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $backup = UserKeyBackup::where('user_id', $user->id)->first();

        if (! $backup) {
            return $this->notFoundResponse('No key backup found.');
        }

        $this->keyBackupService->resetAttempts($backup);

        return $this->successResponse(null, 'Backup access unlocked.');
    }

    /**
     * Update the encrypted key backup.
     */
    public function update(StoreKeyBackupRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $backup = UserKeyBackup::where('user_id', $user->id)->first();

        if (! $backup) {
            return $this->notFoundResponse('No key backup found. Use POST to create.');
        }

        $backup->update([
            'encrypted_bundle' => $validated['encrypted_bundle'],
            'salt' => $validated['salt'],
            'nonce' => $validated['nonce'],
            'argon2_params' => $validated['argon2_params'],
            'version' => $backup->version + 1,
        ]);

        return $this->successResponse(null, 'Key backup updated successfully.');
    }

    /**
     * Delete the encrypted key backup.
     */
    public function destroy(Request $request): JsonResponse
    {
        UserKeyBackup::where('user_id', $request->user()->id)->delete();

        return $this->successResponse(null, 'Key backup deleted.');
    }

    /**
     * Verify a TOTP 2FA code for the user via Fortify's provider.
     */
    private function verifyTwoFactorCode(mixed $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->twoFactorProvider->verify(
            decrypt($user->two_factor_secret),
            $code,
        );
    }
}
