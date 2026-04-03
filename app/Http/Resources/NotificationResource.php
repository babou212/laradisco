<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'type' => class_basename($this->type),
            'data' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    public function toType(Request $request): string
    {
        return 'notifications';
    }
}
