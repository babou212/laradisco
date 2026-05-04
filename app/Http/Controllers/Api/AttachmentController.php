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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AttachmentController extends Controller
{
    use ApiResponse;

    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 MB

    private const PENDING_TTL_HOURS = 24;

    private const DOWNLOAD_URL_TTL_MINUTES = 240;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Upload an attachment for a channel message. The media is staged on the
     * uploading user's `pending_attachments` collection and rebound to a
     * Message when the client posts the message referencing this attachment.
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
     * Upload an attachment for a DM. See uploadForChannel for the staging flow.
     */
    public function uploadForDm(Request $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        return $this->handleUpload($user, $request);
    }

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

        return $this->successResponse([
            'download_url' => $media->getTemporaryUrl($expiration),
            'thumbnail_url' => $media->hasGeneratedConversion('thumb')
                ? $media->getTemporaryUrl($expiration, 'thumb')
                : null,
        ]);
    }

    private function handleUpload(User $user, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.(self::MAX_FILE_SIZE / 1024)],
        ]);

        $media = $user->addMediaFromRequest('file')
            ->withCustomProperties([
                'expires_at' => now()->addHours(self::PENDING_TTL_HOURS)->toIso8601String(),
            ])
            ->toMediaCollection('pending_attachments');

        return $this->successResponse([
            'attachment_id' => $media->uuid,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ]);
    }
}
