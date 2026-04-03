<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;

trait ClearsCaches
{
    /**
     * Boot the trait and register model events for cache clearing.
     */
    public static function bootClearsCaches(): void
    {
        static::saved(function ($model) {
            $model->clearCaches();
            $model->flushCacheTags();
        });

        static::deleted(function ($model) {
            $model->clearCaches();
            $model->flushCacheTags();
        });
    }

    /**
     * Clear relevant caches for this model.
     * Override in models to clear specific caches.
     */
    public function clearCaches(): void
    {
        // Default implementation - override in models
    }

    /**
     * Return cache tags to flush when this model is saved/deleted.
     * Override in models to specify tags.
     *
     * @return list<string>
     */
    public function cacheTags(): array
    {
        return [];
    }

    /**
     * Flush all caches associated with this model's tags.
     */
    public function flushCacheTags(): void
    {
        $tags = $this->cacheTags();
        if (! empty($tags)) {
            Cache::tags($tags)->flush();
        }
    }
}
