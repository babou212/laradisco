<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\InviteLinkResource;
use App\Models\InviteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;
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

        $inviteLinks = QueryBuilder::for(InviteLink::class)
            ->allowedIncludes('creator', 'usedByUser')
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->with(['creator:id,name,username', 'usedByUser:id,name,username'])
            ->cursorPaginate(50);

        return InviteLinkResource::collection($inviteLinks)
            ->includePreviouslyLoadedRelationships()
            ->response();
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

        return (new InviteLinkResource($link))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
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
