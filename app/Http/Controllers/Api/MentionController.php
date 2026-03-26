<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchMentionRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MentionController extends Controller
{
    use ApiResponse;

    /**
     * Search users for @mention autocomplete.
     */
    public function search(SearchMentionRequest $request): JsonResponse
    {
        $search = str_replace(['%', '_'], ['\%', '\_'], $request->validated('q'));

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($q) use ($search) {
                $q->where('username', 'like', $search.'%')
                    ->orWhere('name', 'like', $search.'%')
                    ->orWhere('nickname', 'like', $search.'%');
            })
            ->limit(10)
            ->get();

        return $this->successResponse([
            'users' => UserSummaryResource::collection($users),
        ]);
    }
}
