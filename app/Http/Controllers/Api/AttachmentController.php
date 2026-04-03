<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Enums\AttachmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\EncryptedAttachment;
use App\Models\Message;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use ApiResponse;

    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 MB

    private const DOWNLOAD_EXPIRY = 900; // 15 minutes

    private const NGINX_ACCEL_PREFIX = '/internal-attachments';

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Upload an attachment for a channel message.
     */
    public function uploadForChannel(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        return $this->handleUpload($user, $request);
    }

    /**
     * Upload an attachment for a DM.
     */
    public function uploadForDm(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        return $this->handleUpload($user, $request);
    }

    /**
     * Generate signed download URLs for an attachment.
     */
    public function download(Request $request, EncryptedAttachment $attachment): JsonResponse
    {
        $user = $request->user();

        if ($attachment->status !== AttachmentStatus::Attached) {
            return $this->notFoundResponse();
        }

        if (! $this->userCanAccessAttachment($user, $attachment)) {
            return $this->forbiddenResponse();
        }

        $urls = [
            'download_url' => URL::signedRoute(
                'api.attachments.serve',
                ['attachment' => $attachment->id, 'type' => 'main'],
                now()->addSeconds(self::DOWNLOAD_EXPIRY),
            ),
        ];

        if ($attachment->thumbnail_path) {
            $urls['thumbnail_url'] = URL::signedRoute(
                'api.attachments.serve',
                ['attachment' => $attachment->id, 'type' => 'thumb'],
                now()->addSeconds(self::DOWNLOAD_EXPIRY),
            );
        }

        return $this->successResponse($urls);
    }

    /**
     * Serve an attachment file via signed URL.
     */
    public function serveFile(Request $request, EncryptedAttachment $attachment): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired URL.');
        }

        $type = $request->query('type', 'main');
        $path = $type === 'thumb' ? $attachment->thumbnail_path : $attachment->storage_path;

        if (! $path) {
            abort(404);
        }

        $disk = Storage::disk('attachments');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $fileSize = $disk->size($path);

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $fileSize,
            'Content-Disposition' => 'attachment',
            'Cache-Control' => 'private, no-store',
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

    /**
     * Handle the file upload, store to local disk, and create an attachment record.
     */
    private function handleUpload(mixed $user, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.(self::MAX_FILE_SIZE / 1024)],
            'thumbnail' => ['sometimes', 'file', 'max:5120'],
        ]);

        $attachmentId = Str::uuid()->toString();
        $directory = date('Y/m').'/'.$attachmentId;
        $storagePath = $directory.'/file';
        $thumbnailPath = null;

        $disk = Storage::disk('attachments');

        $disk->putFileAs(
            $directory,
            $request->file('file'),
            'file',
        );

        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $directory.'/thumb';
            $disk->putFileAs(
                $directory,
                $request->file('thumbnail'),
                'thumb',
            );
        }

        $attachment = EncryptedAttachment::create([
            'id' => $attachmentId,
            'user_id' => $user->id,
            'storage_path' => $storagePath,
            'encrypted_size' => $disk->size($storagePath),
            'thumbnail_path' => $thumbnailPath,
            'thumbnail_size' => $thumbnailPath ? $disk->size($thumbnailPath) : null,
            'status' => AttachmentStatus::Attached,
        ]);

        return $this->successResponse([
            'attachment_id' => $attachment->id,
            'encrypted_size' => $attachment->encrypted_size,
            'thumbnail_size' => $attachment->thumbnail_size,
        ]);
    }

    /**
     * Check if the user has access to the attachment through the associated message's channel or DM group.
     */
    private function userCanAccessAttachment(mixed $user, EncryptedAttachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if (! $attachable) {
            return false;
        }

        if ($attachable instanceof Message) {
            $channel = $attachable->channel;

            return $channel && $this->permissionService->userCanViewChannel($user, $channel);
        }

        if ($attachable instanceof DirectMessage) {
            $group = $attachable->group;

            return $group && $group->participants()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    private function supportsAccelRedirect(): bool
    {
        return ! app()->environment('local', 'testing');
    }
}
