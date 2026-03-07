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
        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($q) use ($request) {
                $q->where('username', 'like', $request->input('q').'%')
                    ->orWhere('name', 'like', $request->input('q').'%')
                    ->orWhere('nickname', 'like', $request->input('q').'%');
            })
            ->limit(10)
            ->get();

        return $this->successResponse([
            'users' => UserSummaryResource::collection($users),
        ]);
    }
}
