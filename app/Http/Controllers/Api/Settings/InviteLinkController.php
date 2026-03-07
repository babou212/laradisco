<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\InviteLinkResource;
use App\Models\InviteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InviteLinkController extends Controller
{
    use ApiResponse;

    /**
     * List all invite links (cursor-paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InviteLink::class);

        $inviteLinks = InviteLink::query()
            ->with(['creator:id,name,username', 'usedByUser:id,name,username'])
            ->latest()
            ->cursorPaginate(50);

        return $this->successResponse([
            'invite_links' => InviteLinkResource::collection($inviteLinks->items()),
            'pagination' => [
                'next_cursor' => $inviteLinks->nextCursor()?->encode(),
                'prev_cursor' => $inviteLinks->previousCursor()?->encode(),
                'has_more' => $inviteLinks->hasMorePages(),
            ],
        ]);
    }

    /**
     * Create a new invite link.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', InviteLink::class);

        $link = InviteLink::create([
            'token' => Str::random(64),
            'created_by' => $request->user()->id,
            'expires_at' => now()->addHour(),
        ]);

        $link->load(['creator:id,name,username']);

        return $this->createdResponse(new InviteLinkResource($link));
    }

    /**
     * Delete an invite link.
     */
    public function destroy(Request $request, InviteLink $inviteLink): JsonResponse|Response
    {
        $this->authorize('delete', $inviteLink);

        $inviteLink->delete();

        return $this->noContentResponse();
    }
}
