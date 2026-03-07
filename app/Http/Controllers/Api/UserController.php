<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Show a specific user's public profile.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles' => fn ($query) => $query->orderByDesc('position')]);

        return response()->json((new UserResource($user))->resolve());
    }
}
