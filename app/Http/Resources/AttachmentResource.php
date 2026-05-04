<?php

namespace App\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\RegisteredResponsiveImages;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImage;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/** @mixin Media */
class AttachmentResource extends JsonApiResource
{
    /**
     * Long enough that a viewer can keep streaming a multi-hour video or
     * audio track without the presigned URL expiring mid-playback. Browsers
     * pin the URL once `<video>`/`<audio>` starts buffering, so an expiry
     * shorter than the playback session breaks scrubbing.
     */
    private const URL_TTL_MINUTES = 240;

    /**
     * Use the public Spatie media UUID as the resource id so the API contract
     * (`attachment_ids.*: uuid`) is preserved across the migration.
     */
    public function toId(Request $request): string
    {
        return (string) $this->uuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $expiration = now()->addMinutes(self::URL_TTL_MINUTES);
        $responsiveImages = $this->responsiveImages();

        $hasThumbnail = $this->hasGeneratedConversion('thumb');

        return [
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->getCustomProperty('width'),
            'height' => $this->getCustomProperty('height'),
            'has_thumbnail' => $hasThumbnail,
            'thumbnail_size' => $this->getCustomProperty('thumbnail_size'),
            'url' => $this->getTemporaryUrl($expiration),
            'thumbnail_url' => $hasThumbnail
                ? $this->getTemporaryUrl($expiration, 'thumb')
                : null,
            'srcset' => $responsiveImages !== null ? $this->buildResponsiveSources($expiration) : null,
            'placeholder' => $responsiveImages?->getPlaceholderSvg(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array<int, array{url: string, width: int}>|null
     */
    private function buildResponsiveSources(DateTimeInterface $expiration): ?array
    {
        $registered = $this->responsiveImages();

        if ($registered === null || $registered->files->isEmpty()) {
            return null;
        }

        /** @var Media $media */
        $media = $this->resource;
        $disk = Storage::disk($media->disk);
        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);

        return $registered->files
            ->map(fn (ResponsiveImage $r): array => [
                'url' => $disk->temporaryUrl($directory.$r->fileName, $expiration),
                'width' => $r->width(),
            ])
            ->values()
            ->all();
    }

    private function responsiveImages(): ?RegisteredResponsiveImages
    {
        /** @var Media $media */
        $media = $this->resource;
        $registered = $media->responsiveImages();

        return $registered->files->isEmpty() && $registered->getPlaceholderSvg() === null
            ? null
            : $registered;
    }
}
