<?php

namespace App\Http\Resources;

use App\Models\EncryptedAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin EncryptedAttachment */
class EncryptedAttachmentResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'encrypted_size' => $this->encrypted_size,
            'has_thumbnail' => fn () => $this->thumbnail_path !== null,
            'thumbnail_size' => $this->thumbnail_size,
            'created_at' => $this->created_at,
        ];
    }
}
