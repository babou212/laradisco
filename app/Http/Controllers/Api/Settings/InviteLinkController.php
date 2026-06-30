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

/**
 * @group Settings: Invite Links
 */
class InviteLinkController extends Controller
{
    use ApiResponse;

    /**
     * List all invite links (cursor-paginated).
     *
     * @queryParam include string Comma-separated relations to embed. Allowed: creator, usedByUser. Example: creator
     * @queryParam sort string Sort field; prefix - for descending. Allowed: created_at. Example: -created_at
     *
     * @response 200 {"data":[{"type":"invite_links","id":"1","attributes":{"token":"a1b2c3","created_by":3,"used_by":null,"used_at":null,"expires_at":"2026-06-30T13:00:00.000000Z","created_at":"2026-06-30T12:00:00.000000Z"}}]}
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InviteLink::class);

        $inviteLinks = QueryBuilder::for(InviteLink::class)
            ->allowedIncludes('creator', 'usedByUser')
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->with(['creator:id,username', 'usedByUser:id,username'])
            ->cursorPaginate(50);

        return InviteLinkResource::collection($inviteLinks)
            ->includePreviouslyLoadedRelationships()
            ->response();
    }

    /**
     * Create a new invite link.
     *
     * @response 201 {"data":{"type":"invite_links","id":"2","attributes":{"token":"x9y8z7","created_by":3,"used_by":null,"used_at":null,"expires_at":"2026-06-30T13:00:00.000000Z","created_at":"2026-06-30T12:00:00.000000Z"}}}
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', InviteLink::class);

        $link = InviteLink::create([
            'token' => Str::random(64),
            'created_by' => $request->user()->id,
            'expires_at' => now()->addHour(),
        ]);

        $link->load(['creator:id,username']);

        return (new InviteLinkResource($link))
            ->includePreviouslyLoadedRelationships()
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete an invite link.
     *
     * @response 204
     */
    public function destroy(Request $request, InviteLink $inviteLink): JsonResponse|Response
    {
        $this->authorize('delete', $inviteLink);

        $inviteLink->delete();

        return $this->noContentResponse();
    }
}
