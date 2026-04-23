<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaServeController extends Controller
{
    use ApiResponse;

    private const NGINX_ACCEL_PREFIX = '/internal-media';

    public function show(Request $request, Media $media, ?string $conversion = null): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired URL.');
        }

        $disk = Storage::disk($media->disk);

        $path = $conversion
            ? $media->getPathRelativeToRoot($conversion)
            : $media->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mimeType = $media->mime_type ?? 'application/octet-stream';
        $fileSize = $disk->size($path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Content-Disposition' => 'inline; filename="'.addcslashes($media->file_name, '"\\').'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->supportsAccelRedirect()) {
            $accelPath = self::NGINX_ACCEL_PREFIX.'/'.ltrim($path, '/');

            return response('', 200, array_merge($headers, [
                'X-Accel-Redirect' => $accelPath,
            ]));
        }

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    private function supportsAccelRedirect(): bool
    {
        return ! app()->environment('local', 'testing');
    }
}
