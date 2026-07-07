<?php

namespace App\Listeners;

use App\Events\UserProfileUpdated;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class BroadcastAvatarConversionCompleted
{
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        $media = $event->media;

        if ($media->collection_name !== 'avatar' || $media->model_type !== User::class) {
            return;
        }

        if (! $media->hasGeneratedConversion('thumb')
            || ! $media->hasGeneratedConversion('small')
            || ! $media->hasGeneratedConversion('medium')) {
            return;
        }

        $user = User::find($media->model_id);

        if (! $user) {
            return;
        }

        Cache::tags([CacheKeys::userTag($user->id)])->forget(CacheKeys::userProfile($user->id));

        UserProfileUpdated::dispatch($user);
    }
}
