<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\StoreKeyBackupRequest;
use App\Models\UserKeyBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeyBackupController extends Controller
{
    use ApiResponse;

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
     */
    public function show(Request $request): JsonResponse
    {
        $backup = UserKeyBackup::where('user_id', $request->user()->id)->first();

        if (! $backup) {
            return $this->notFoundResponse('No key backup found.');
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
}
