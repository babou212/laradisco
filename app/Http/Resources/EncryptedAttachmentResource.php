<?php

namespace App\Http\Resources;

use App\Models\EncryptedAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EncryptedAttachment */
class EncryptedAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encrypted_size' => $this->encrypted_size,
            'has_thumbnail' => $this->thumbnail_path !== null,
            'thumbnail_size' => $this->thumbnail_size,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
