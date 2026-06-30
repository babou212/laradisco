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

/**
 * @group Settings: Two-Factor
 */
class TwoFactorController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EnableTwoFactorAuthentication $enableTwoFactor,
        private readonly DisableTwoFactorAuthentication $disableTwoFactor,
        private readonly GenerateNewRecoveryCodes $generateRecoveryCodes,
        private readonly ConfirmTwoFactorAuthentication $confirmTwoFactor,
    ) {}

    /**
     * Get 2FA status for the authenticated user.
     *
     * @response 200 {"data":{"twoFactorEnabled":false,"requiresConfirmation":true}}
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
     *
     * @response 200 {"message":"Two-factor authentication enabled successfully"}
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $this->validationErrorResponse('Two-factor authentication is already enabled.');
        }

        ($this->enableTwoFactor)($user);

        return $this->successResponse(message: 'Two-factor authentication enabled successfully');
    }

    /**
     * Disable two-factor authentication.
     *
     * @response 200 {"message":"Two-factor authentication disabled successfully"}
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return $this->validationErrorResponse('Two-factor authentication is not enabled.');
        }

        ($this->disableTwoFactor)($user);

        return $this->successResponse(message: 'Two-factor authentication disabled successfully');
    }

    /**
     * Get the QR code SVG and URL.
     *
     * @response 200 {"data":{"svg":"<svg viewBox=\"0 0 100 100\">...</svg>","url":"otpauth://totp/Laradisco:alice@example.com?secret=ABCDEF&issuer=Laradisco"}}
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
     *
     * @response 200 {"data":{"secretKey":"ABCDEFGHIJKLMNOP"}}
     */
    public function secretKey(Request $request): JsonResponse
    {
        return $this->successResponse([
            'secretKey' => decrypt($request->user()->two_factor_secret),
        ]);
    }

    /**
     * Get recovery codes.
     *
     * @response 200 {"data":{"recovery_codes":["aaaa-bbbb","cccc-dddd"]}}
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $codes = json_decode(decrypt($request->user()->two_factor_recovery_codes), true);

        return $this->successResponse(['recovery_codes' => $codes]);
    }

    /**
     * Regenerate recovery codes.
     *
     * @response 200 {"data":{"recovery_codes":["eeee-ffff","gggg-hhhh"]}}
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        ($this->generateRecoveryCodes)($user);

        $codes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);

        return $this->successResponse(['recovery_codes' => $codes]);
    }

    /**
     * Confirm two-factor authentication setup.
     *
     * @response 200 {"message":"Two-factor authentication confirmed successfully"}
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        ($this->confirmTwoFactor)($user, $request->validated('code'));

        return $this->successResponse(message: 'Two-factor authentication confirmed successfully');
    }
}
