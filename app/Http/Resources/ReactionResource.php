<?php

namespace App\Http\Resources;

use App\Models\MessageReaction;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin MessageReaction */
class ReactionResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        'user_id',
        'emoji',
        'message_id',
        'created_at',
    ];
}
