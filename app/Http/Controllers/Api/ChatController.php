<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Category;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Return the sidebar data: categories with their accessible channels.
     */
    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleChannelIds = $this->permissionService
            ->getAccessibleChannels($user)
            ->pluck('id')
            ->all();

        $categories = Category::with(['channels' => function ($query) use ($accessibleChannelIds) {
            $query->whereIn('id', $accessibleChannelIds)
                ->orderBy('position');
        }])
            ->orderBy('position')
            ->get()
            ->filter(fn (Category $category) => $category->channels->isNotEmpty())
            ->values();

        return $this->successResponse(CategoryResource::collection($categories));
    }

    /**
     * Get server members (cursor-paginated, with optional search).
     */
    public function members(Request $request): JsonResponse
    {
        $query = User::select(['id', 'name', 'username', 'nickname', 'avatar_path', 'status', 'custom_status'])
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', $search.'%')
                    ->orWhere('name', 'like', $search.'%')
                    ->orWhere('nickname', 'like', $search.'%');
            });
        }

        $users = $query->cursorPaginate(50);

        return $this->successResponse([
            'members' => UserSummaryResource::collection($users->items()),
            'pagination' => [
                'next_cursor' => $users->nextCursor()?->encode(),
                'prev_cursor' => $users->previousCursor()?->encode(),
                'has_more' => $users->hasMorePages(),
            ],
        ]);
    }
}
