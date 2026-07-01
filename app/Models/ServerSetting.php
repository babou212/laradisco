<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ServerSetting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['afk_channel_id', 'afk_timeout'];

    /** Returns the single server-settings row, creating it on first access. */
    public static function instance(): static
    {
        return static::first() ?? static::create();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media?->collection_name !== 'logo') {
            return;
        }

        $this->addMediaConversion('thumb')
            ->width(64)
            ->height(64)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(256)
            ->height(256)
            ->nonQueued();
    }

    /**
     * @return array{thumb: string|null, medium: string|null, original: string}|null
     */
    public function getLogoUrlsAttribute(): ?array
    {
        $media = $this->getFirstMedia('logo');

        if (! $media) {
            return null;
        }

        $url = fn (string $size): string => route('api.server.logo', [
            'version' => $media->id,
            'size' => $size,
        ]);

        return [
            'thumb' => $media->hasGeneratedConversion('thumb') ? $url('thumb') : null,
            'medium' => $media->hasGeneratedConversion('medium') ? $url('medium') : null,
            'original' => $url('original'),
        ];
    }
}
