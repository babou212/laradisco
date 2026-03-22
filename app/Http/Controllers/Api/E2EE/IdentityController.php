<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\RegisterIdentityRequest;
use App\Models\MlsKeyPackage;
use App\Models\MlsWelcomeMessage;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserIdentityKey;
use App\Models\UserKeyBackup;
use App\Services\E2eeAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentityController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly E2eeAuditService $auditService,
    ) {}

    /**
     * Register the user's identity key (first-time E2EE setup only).
     */
    public function register(RegisterIdentityRequest $request): JsonResponse
    {
        $user = $request->user();

        if (UserIdentityKey::where('user_id', $user->id)->exists()) {
            return $this->errorResponse(
                'Identity key already registered. Use key backup to restore on new devices.',
                Response::HTTP_CONFLICT,
            );
        }

        $identityKey = UserIdentityKey::create([
            'user_id' => $user->id,
            'identity_key' => $request->validated('identity_key'),
        ]);

        $this->auditService->logEvent(
            userId: $user->id,
            eventType: 'identity_key_registered',
            publicKey: $request->validated('identity_key'),
        );

        return $this->createdResponse([
            'user_id' => $user->id,
            'identity_key' => $request->validated('identity_key'),
        ], 'Identity key registered successfully.');
    }

    /**
     * Get a user's public identity key.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $identityKey = UserIdentityKey::where('user_id', $user->id)->first();

        if (! $identityKey) {
            return $this->notFoundResponse('User has not set up E2EE.');
        }

        return $this->successResponse([
            'user_id' => $user->id,
            'identity_key' => $identityKey->identity_key,
        ]);
    }

    /**
     * Reset all E2EE data for the authenticated user.
     * Wipes identity key, devices, MLS data, and backup data,
     * then records an `identity_reset` event in the audit log.
     * After calling this, the user can re-register their identity.
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! UserIdentityKey::where('user_id', $user->id)->exists()) {
            return $this->notFoundResponse('No E2EE identity found to reset.');
        }

        DB::transaction(function () use ($user) {
            MlsKeyPackage::where('user_id', $user->id)->delete();
            MlsWelcomeMessage::where('recipient_user_id', $user->id)->delete();

            UserDevice::where('user_id', $user->id)->delete();

            UserKeyBackup::where('user_id', $user->id)->delete();

            UserIdentityKey::where('user_id', $user->id)->delete();
        });

        $this->auditService->logEvent(
            userId: $user->id,
            eventType: 'identity_reset',
        );

        return $this->successResponse(null, 'E2EE identity and all associated data have been reset.');
    }
}
