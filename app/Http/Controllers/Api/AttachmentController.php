<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\Message;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * @group Attachments
 */
class AttachmentController extends Controller
{
    use ApiResponse;

    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 MB

    private const PENDING_TTL_HOURS = 24;

    private const DOWNLOAD_URL_TTL_MINUTES = 240;

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * Upload a channel attachment
     *
     * Upload an attachment for a channel message. The media is staged on the
     * uploading user's `pending_attachments` collection and rebound to a
     * Message when the client posts the message referencing this attachment.
     * The returned `attachment_id` is what you pass in `attachment_ids` when
     * sending the message.
     *
     * @bodyParam file file required The file to upload (max 100 MB).
     * @bodyParam thumbnail file A client-generated thumbnail image (optional).
     * @bodyParam width integer Intrinsic media width in pixels. Example: 1920
     * @bodyParam height integer Intrinsic media height in pixels. Example: 1080
     *
     * @response 200 {"data": {"attachment_id": "9b1f2c34-...", "file_name": "clip.mp4", "mime_type": "video/mp4", "size": 5242880, "thumbnail_size": 20480}}
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
     * Upload a DM attachment
     *
     * Upload an attachment for a DM. See uploadForChannel for the staging flow.
     *
     * @bodyParam file file required The file to upload (max 100 MB).
     * @bodyParam thumbnail file A client-generated thumbnail image (optional).
     * @bodyParam width integer Intrinsic media width in pixels. Example: 1920
     * @bodyParam height integer Intrinsic media height in pixels. Example: 1080
     *
     * @response 200 {"data": {"attachment_id": "9b1f2c34-...", "file_name": "clip.mp4", "mime_type": "video/mp4", "size": 5242880, "thumbnail_size": 20480}}
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
     * Get an attachment download URL
     *
     * Returns short-lived presigned URLs for the attachment (and its thumbnail,
     * if one exists). The caller must have access to the channel or DM the
     * attachment belongs to.
     *
     * @response 200 {"data": {"download_url": "https://.../clip.mp4?signature=...", "thumbnail_url": "https://.../thumb.webp?signature=..."}}
     */
    public function download(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $media = Media::query()->where('uuid', $uuid)->first();

        if (! $media || $media->collection_name !== 'attachments') {
            return $this->notFoundResponse('Attachment not found');
        }

        $owner = $media->model;

        $authorized = match (true) {
            $owner instanceof Message => $owner->channel !== null
                && $this->permissionService->userCanViewChannel($user, $owner->channel),
            $owner instanceof DirectMessage => $owner->group !== null
                && $owner->group->participants()->where('users.id', $user->id)->exists(),
            default => false,
        };

        if (! $authorized) {
            return $this->forbiddenResponse();
        }

        $expiration = now()->addMinutes(self::DOWNLOAD_URL_TTL_MINUTES);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $media->file_name,
        );

        return $this->successResponse([
            'download_url' => $media->getTemporaryUrl($expiration, '', [
                'ResponseContentDisposition' => $disposition,
            ]),
            'thumbnail_url' => $media->hasGeneratedConversion('thumb')
                ? $media->getTemporaryUrl($expiration, 'thumb')
                : null,
        ]);
    }

    private function handleUpload(User $user, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.(self::MAX_FILE_SIZE / 1024)],
            'thumbnail' => ['nullable', 'file', 'image'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
        ]);

        $thumbnail = $request->file('thumbnail');

        $customProperties = [
            'expires_at' => now()->addHours(self::PENDING_TTL_HOURS)->toIso8601String(),
        ];

        if ($request->filled('width')) {
            $customProperties['width'] = (int) $request->input('width');
        }
        if ($request->filled('height')) {
            $customProperties['height'] = (int) $request->input('height');
        }
        if ($thumbnail) {
            $customProperties['thumbnail_size'] = $thumbnail->getSize();
        }

        $media = $user->addMediaFromRequest('file')
            ->withCustomProperties($customProperties)
            ->toMediaCollection('pending_attachments');

        if ($thumbnail) {
            $this->storeThumbnailConversion($media, $thumbnail);
        }

        return $this->successResponse([
            'attachment_id' => $media->uuid,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'thumbnail_size' => $customProperties['thumbnail_size'] ?? null,
        ]);
    }

    /**
     * Persist a client-supplied thumbnail as the media's `thumb` conversion so thumbnails are
     * available without server-side ffmpeg generation (the client already extracts a webp frame
     * for videos). This mirrors the deterministic webp file name of the `thumb` conversion
     * declared on the Message / DirectMessage models the media is later rebound to, so the
     * download endpoint resolves the same path via getTemporaryUrl(..., 'thumb').
     */
    private function storeThumbnailConversion(Media $media, UploadedFile $thumbnail): void
    {
        $conversion = Conversion::create('thumb')->format('webp');

        $this->filesystem->copyToMediaLibrary(
            $thumbnail->getRealPath(),
            $media,
            'conversions',
            $conversion->getConversionFile($media),
        );

        $media->markAsConversionGenerated('thumb');
    }
}
