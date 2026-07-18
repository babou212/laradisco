<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\E2eeAuditLog;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * Get the key transparency audit log for a user.
     */
    public function index(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id) {
            return $this->forbiddenResponse('You can only access your own audit log.');
        }

        $since = $request->query('since');

        $query = E2eeAuditLog::where('user_id', $user->id)
            ->orderBy('id', 'asc');

        if ($since) {
            $query->where('created_at', '>', $since);
        }

        $entries = $query->get()->map(fn (E2eeAuditLog $entry) => [
            'id' => $entry->id,
            'event_type' => $entry->event_type,
            'device_id' => $entry->device_id,
            'public_key' => $entry->public_key,
            'signature' => $entry->signature,
            'metadata' => $entry->metadata,
            'previous_hash' => $entry->previous_hash,
            'entry_hash' => $entry->entry_hash,
            'created_at' => $entry->created_at?->toISOString(),
        ]);

        return $this->successResponse($entries);
    }

    /**
     * Get the latest hash from the audit log for a user.
     */
    public function latestHash(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id) {
            return $this->forbiddenResponse('You can only access your own audit log.');
        }

        $cacheKey = CacheKeys::e2eeAuditLatest($user->id);
        $cached = Cache::tags([CacheKeys::userTag($user->id)])->get($cacheKey);
        if ($cached) {
            return $this->successResponse($cached);
        }

        $latestEntry = E2eeAuditLog::where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $data = [
            'latest_hash' => $latestEntry?->entry_hash,
            'entry_count' => E2eeAuditLog::where('user_id', $user->id)->count(),
        ];

        Cache::tags([CacheKeys::userTag($user->id)])
            ->put($cacheKey, $data, CacheKeys::TTL_WARM);

        return $this->successResponse($data);
    }
}
