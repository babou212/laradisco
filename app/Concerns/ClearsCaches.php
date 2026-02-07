<?php

namespace App\Concerns;

trait ClearsCaches
{
    /**
     * Boot the trait and register model events for cache clearing.
     */
    public static function bootClearsCaches(): void
    {
        static::saved(function ($model) {
            $model->clearCaches();
        });

        static::deleted(function ($model) {
            $model->clearCaches();
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
}
