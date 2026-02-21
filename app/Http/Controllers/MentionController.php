<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    /**
     * Search users for @mention autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($q) use ($query) {
                $q->where('username', 'like', $query['q'].'%')
                    ->orWhere('name', 'like', $query['q'].'%')
                    ->orWhere('nickname', 'like', $query['q'].'%');
            })
            ->select(['id', 'username', 'name', 'nickname', 'avatar_path'])
            ->limit(10)
            ->get();

        return response()->json(['users' => $users]);
    }
}
