<?php

namespace App\Http\Resources;

use App\Models\SoundboardSound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin SoundboardSound */
class SoundResource extends JsonApiResource
{
    /**
     * Long enough that a triggered clip can be fetched and played without the
     * presigned URL expiring (clients download the file on demand).
     */
    private const URL_TTL_MINUTES = 240;

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $media = $this->soundMedia();

        return [
            'name' => $this->name,
            'duration_ms' => $this->duration_ms,
            'url' => $media?->getTemporaryUrl(now()->addMinutes(self::URL_TTL_MINUTES)),
            'mime_type' => $media?->mime_type,
            'uploaded_by_id' => $this->user_id,
            'uploaded_by' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => (string) $this->user->id,
                'username' => $this->user->username,
                'display_name' => $this->user->display_name,
                'avatar_urls' => $this->user->avatar_urls,
            ]),
            'created_at' => $this->created_at,
        ];
    }

    public function toType(Request $request): string
    {
        return 'sounds';
    }
}
