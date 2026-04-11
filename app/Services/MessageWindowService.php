<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;

class MessageWindowService
{
    public const HALF_WINDOW = 25;

    /**
     * Build a window of messages centered around $target ordered by created_at asc.
     *
     * Returns the merged item collection plus cursor strings that the frontend
     * can pass back to the normal cursor-paginated listing endpoint as
     * `?cursor=<prev|next>` to continue loading older/newer messages.
     *
     * @param  Builder<Model>  $baseQuery  a fresh query scoped to the container (channel / dm group)
     * @return array{items: Collection<int, Model>, prevCursor: ?string, nextCursor: ?string}
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

        $hasPrev = $beforeRaw->count() > $halfWindow;
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

        $hasNext = $afterRaw->count() > $halfWindow;
        $after = $afterRaw->take($halfWindow)->values();

        /** @var Collection<int, Model> $items */
        $items = $before->push($target)->concat($after)->values();

        $prevCursor = null;
        $nextCursor = null;

        if ($hasPrev && $items->isNotEmpty()) {
            $first = $items->first();
            $prevCursor = (new Cursor([
                'created_at' => $first->getAttribute('created_at'),
                'id' => $first->getKey(),
            ], false))->encode();
        }

        if ($hasNext && $items->isNotEmpty()) {
            $last = $items->last();
            $nextCursor = (new Cursor([
                'created_at' => $last->getAttribute('created_at'),
                'id' => $last->getKey(),
            ], true))->encode();
        }

        return [
            'items' => $items,
            'prevCursor' => $prevCursor,
            'nextCursor' => $nextCursor,
        ];
    }
}
