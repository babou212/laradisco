<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Category */
class CategoryResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     *
     * @var list<string>
     */
    public $attributes = [
        'name',
        'position',
        'created_at',
    ];

    /**
     * The resource's relationships.
     *
     * @var array<string, class-string>
     */
    public $relationships = [
        'channels' => ChannelResource::class,
    ];
}
