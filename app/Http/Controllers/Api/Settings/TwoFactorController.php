<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmTwoFactorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;

class TwoFactorController extends Controller
{
    use ApiResponse;

    /**
     * Get 2FA status for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse([
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(
                Features::twoFactorAuthentication(), 'confirm'
            ),
        ]);
    }

    /**
     * Enable two-factor authentication.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $this->validationErrorResponse('Two-factor authentication is already enabled.');
        }

        app(EnableTwoFactorAuthentication::class)($user);

        return $this->successResponse(message: 'Two-factor authentication enabled successfully');
    }

    /**
     * Disable two-factor authentication.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return $this->validationErrorResponse('Two-factor authentication is not enabled.');
        }

        app(DisableTwoFactorAuthentication::class)($user);

        return $this->successResponse(message: 'Two-factor authentication disabled successfully');
    }

    /**
     * Get the QR code SVG and URL.
     */
    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'svg' => $user->twoFactorQrCodeSvg(),
            'url' => $user->twoFactorQrCodeUrl(),
        ]);
    }

    /**
     * Get the secret key.
     */
    public function secretKey(Request $request): JsonResponse
    {
        return $this->successResponse([
            'secretKey' => decrypt($request->user()->two_factor_secret),
        ]);
    }

    /**
     * Get recovery codes.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $codes = json_decode(decrypt($request->user()->two_factor_recovery_codes), true);

        return $this->successResponse(['recovery_codes' => $codes]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        app(GenerateNewRecoveryCodes::class)($user);

        $codes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);

        return $this->successResponse(['recovery_codes' => $codes]);
    }

    /**
     * Confirm two-factor authentication setup.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        app(ConfirmTwoFactorAuthentication::class)($user, $request->validated('code'));

        return $this->successResponse(message: 'Two-factor authentication confirmed successfully');
    }
}
