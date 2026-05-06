<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MessageWindowService
{
    public const HALF_WINDOW = 25;

    /**
     * Build a window of messages centered around $target ordered by created_at asc.
     *
     * @param  Builder<Model>  $baseQuery  a fresh query scoped to the container (channel / dm group)
     * @return array{items: Collection<int, Model>, hasMoreBefore: bool, hasMoreAfter: bool, oldestId: ?string, newestId: ?string}
     */
    public function windowAround(Builder $baseQuery, Model $target, int $halfWindow = self::HALF_WINDOW): array
    {
        $targetTime = $target->getAttribute('created_at');
        $targetKey = $target->getKey();

        $beforeRaw = (clone $baseQuery)
            ->where(function ($q) use ($targetTime, $targetKey) {
                $q->where('created_at', '<', $targetTime)
                    ->orWhere(function ($q2) use ($targetTime, $targetKey) {
                        $q2->where('created_at', '=', $targetTime)
                            ->where('id', '<', $targetKey);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($halfWindow + 1)
            ->get();

        $hasMoreBefore = $beforeRaw->count() > $halfWindow;
        $before = $beforeRaw->take($halfWindow)->reverse()->values();

        $afterRaw = (clone $baseQuery)
            ->where(function ($q) use ($targetTime, $targetKey) {
                $q->where('created_at', '>', $targetTime)
                    ->orWhere(function ($q2) use ($targetTime, $targetKey) {
                        $q2->where('created_at', '=', $targetTime)
                            ->where('id', '>', $targetKey);
                    });
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($halfWindow + 1)
            ->get();

        $hasMoreAfter = $afterRaw->count() > $halfWindow;
        $after = $afterRaw->take($halfWindow)->values();

        /** @var Collection<int, Model> $items */
        $items = $before->push($target)->concat($after)->values();

        $oldestId = $items->isNotEmpty() ? (string) $items->first()->getKey() : null;
        $newestId = $items->isNotEmpty() ? (string) $items->last()->getKey() : null;

        return [
            'items' => $items,
            'hasMoreBefore' => $hasMoreBefore,
            'hasMoreAfter' => $hasMoreAfter,
            'oldestId' => $oldestId,
            'newestId' => $newestId,
        ];
    }
}
