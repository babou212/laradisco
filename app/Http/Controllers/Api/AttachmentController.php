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
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    use ApiResponse;

    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 MB

    private const PRESIGN_EXPIRY = 300; // 5 minutes

    private const DOWNLOAD_EXPIRY = 900; // 15 minutes

    private const PENDING_TTL_HOURS = 1;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Generate presigned upload URLs for a channel attachment.
     */
    public function presignForChannel(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService->userCanViewChannel($user, $channel)) {
            return $this->forbiddenResponse('You do not have access to this channel.');
        }

        return $this->generatePresignedUpload($user, $request);
    }

    /**
     * Generate presigned upload URLs for a DM attachment.
     */
    public function presignForDm(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        return $this->generatePresignedUpload($user, $request);
    }

    /**
     * Confirm that an upload has completed.
     */
    public function confirm(Request $request, EncryptedAttachment $attachment): JsonResponse
    {
        if ($attachment->user_id !== $request->user()->id) {
            return $this->forbiddenResponse();
        }

        if ($attachment->status !== AttachmentStatus::Pending) {
            return $this->validationErrorResponse('Attachment is not in pending state.');
        }

        $disk = Storage::disk('s3');

        if (! $disk->exists($attachment->storage_path)) {
            return $this->validationErrorResponse('File not found on storage. Upload may not have completed.');
        }

        $attachment->update([
            'encrypted_size' => $disk->size($attachment->storage_path),
            'thumbnail_size' => $attachment->thumbnail_path && $disk->exists($attachment->thumbnail_path)
                ? $disk->size($attachment->thumbnail_path)
                : null,
            'expires_at' => now()->addHours(self::PENDING_TTL_HOURS),
        ]);

        return $this->successResponse([
            'id' => $attachment->id,
            'encrypted_size' => $attachment->encrypted_size,
            'thumbnail_size' => $attachment->thumbnail_size,
        ]);
    }

    /**
     * Generate a presigned download URL for an attachment.
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

        $disk = Storage::disk('s3');
        $presignDisk = Storage::disk('s3-presign');

        $urls = [
            'download_url' => $presignDisk->temporaryUrl($attachment->storage_path, now()->addSeconds(self::DOWNLOAD_EXPIRY)),
        ];

        if ($attachment->thumbnail_path) {
            $urls['thumbnail_url'] = $presignDisk->temporaryUrl($attachment->thumbnail_path, now()->addSeconds(self::DOWNLOAD_EXPIRY));
        }

        return $this->successResponse($urls);
    }

    /**
     * Generate presigned upload URLs and create a pending attachment record.
     */
    private function generatePresignedUpload(mixed $user, Request $request): JsonResponse
    {
        $request->validate([
            'file_size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_FILE_SIZE],
            'has_thumbnail' => ['sometimes', 'boolean'],
        ]);

        $attachmentId = Str::uuid()->toString();
        $storagePath = 'attachments/'.date('Y/m').'/'.$attachmentId;
        $thumbnailPath = null;

        $disk = Storage::disk('s3');

        $presignDisk = Storage::disk('s3-presign');

        $uploadUrl = $presignDisk->temporaryUploadUrl($storagePath, now()->addSeconds(self::PRESIGN_EXPIRY));

        $response = [
            'attachment_id' => $attachmentId,
            'upload_url' => $uploadUrl['url'],
            'upload_headers' => $uploadUrl['headers'] ?? [],
        ];

        if ($request->boolean('has_thumbnail')) {
            $thumbnailPath = $storagePath.'/thumb';
            $thumbUpload = $presignDisk->temporaryUploadUrl($thumbnailPath, now()->addSeconds(self::PRESIGN_EXPIRY));
            $response['thumbnail_upload_url'] = $thumbUpload['url'];
            $response['thumbnail_upload_headers'] = $thumbUpload['headers'] ?? [];
        }

        EncryptedAttachment::create([
            'id' => $attachmentId,
            'user_id' => $user->id,
            'storage_path' => $storagePath,
            'thumbnail_path' => $thumbnailPath,
            'status' => AttachmentStatus::Pending,
            'expires_at' => now()->addHours(self::PENDING_TTL_HOURS),
        ]);

        return $this->successResponse($response);
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
}
