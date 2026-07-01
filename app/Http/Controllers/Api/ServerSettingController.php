<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadServerLogoRequest;
use App\Models\ServerSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerSettingController extends Controller
{
    use ApiResponse;

    /**
     * Return current server settings (logo URLs + AFK configuration).
     */
    public function show(): JsonResponse
    {
        $settings = ServerSetting::instance();

        return response()->json([
            'logo_urls' => $settings->logo_urls,
            'afk_channel_id' => $settings->afk_channel_id,
            'afk_timeout' => $settings->afk_timeout,
        ]);
    }

    /**
     * Update server settings (AFK channel / timeout).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdministrator() && ! $user->hasPermissionTo('manage_server')) {
            return $this->forbiddenResponse('You do not have permission to manage server settings.');
        }

        $validated = $request->validate([
            'afk_channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'afk_timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $settings = ServerSetting::instance();
        $settings->fill($validated)->save();

        return response()->json([
            'logo_urls' => $settings->logo_urls,
            'afk_channel_id' => $settings->afk_channel_id,
            'afk_timeout' => $settings->afk_timeout,
        ]);
    }

    /**
     * Upload or replace the server logo.
     */
    public function uploadLogo(UploadServerLogoRequest $request): JsonResponse
    {
        $settings = ServerSetting::instance();

        $settings->addMediaFromRequest('logo')
            ->toMediaCollection('logo');

        $fresh = $settings->refresh();

        return response()->json(['logo_urls' => $fresh->logo_urls]);
    }

    /**
     * Delete the server logo.
     */
    public function deleteLogo(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        if (! $user->isAdministrator() && ! $user->hasPermissionTo('manage_server')) {
            return $this->forbiddenResponse('You do not have permission to manage server settings.');
        }

        ServerSetting::instance()->clearMediaCollection('logo');

        return $this->noContentResponse();
    }

    /**
     * Stream the server logo image (a given Spatie conversion).
     */
    public function streamLogo(string $version, string $size): StreamedResponse
    {
        $settings = ServerSetting::first();
        abort_if($settings === null, 404);

        $media = $settings->getFirstMedia('logo');
        abort_if($media === null, 404);

        $conversion = $size === 'original' ? '' : $size;
        abort_if($conversion !== '' && ! $media->hasGeneratedConversion($conversion), 404);

        return response()->stream(function () use ($media, $conversion) {
            $stream = $media->stream($conversion);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=2592000, immutable',
            'ETag' => '"'.$media->id.'-'.$size.'"',
        ]);
    }
}
