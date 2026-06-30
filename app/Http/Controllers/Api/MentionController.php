<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchMentionRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Mentions
 */
class MentionController extends Controller
{
    use ApiResponse;

    /**
     * Search users for mentions
     *
     * Search users for @mention autocomplete. Matches usernames by prefix and
     * returns up to 10 results, excluding the requesting user.
     *
     * @queryParam q string required Username prefix to match. Example: ali
     * @queryParam sort string Sort field; prefix - for descending. Allowed: username. Example: username
     *
     * @response 200 {"data": [{"type": "users", "id": "8", "attributes": {"username": "alice", "display_name": "Alice", "avatar_urls": null, "status": "online", "custom_status": null, "created_at": "2026-06-01T09:00:00.000000Z"}}]}
     */
    public function search(SearchMentionRequest $request): JsonResponse
    {
        $search = str_replace(['%', '_'], ['\%', '\_'], $request->validated('q'));

        $users = QueryBuilder::for(
            User::where('id', '!=', $request->user()->id)
                ->where('username', 'like', $search.'%')
        )
            ->allowedSorts('username')
            ->limit(10)
            ->get();

        return UserSummaryResource::collection($users)
            ->response();
    }
}
