<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageEdited;
use App\Events\DirectMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateDirectMessageGroupRequest;
use App\Http\Requests\Api\CursorPaginateRequest;
use App\Http\Requests\Api\FindDirectMessageGroupRequest;
use App\Http\Requests\Api\StoreDirectMessageRequest;
use App\Http\Requests\Api\UpdateDirectMessageRequest;
use App\Http\Resources\DirectMessageGroupResource;
use App\Http\Resources\DirectMessageResource;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Notifications\DirectMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DirectMessageController extends Controller
{
    use ApiResponse;

    /**
     * List all DM groups for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $dmGroups = $user->directMessageGroups()
            ->with(['participants', 'lastMessage.user'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return $this->successResponse(DirectMessageGroupResource::collection($dmGroups));
    }

    /**
     * Show a specific DM group with messages.
     */
    public function show(CursorPaginateRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        $otherParticipant = $dmGroup->participants()
            ->where('users.id', '!=', $user->id)
            ->first();

        $messages = $dmGroup->messages()
            ->with(['user', 'reactions'])
            ->orderBy('created_at', 'asc')
            ->cursorPaginate(50);

        return $this->successResponse([
            'dm_group' => new DirectMessageGroupResource($dmGroup->load('participants')),
            'messages' => DirectMessageResource::collection($messages->items()),
            'pagination' => [
                'next_cursor' => $messages->nextCursor()?->encode(),
                'prev_cursor' => $messages->previousCursor()?->encode(),
                'has_more' => $messages->hasMorePages(),
            ],
        ]);
    }

    /**
     * Send a message in a DM group.
     */
    public function store(StoreDirectMessageRequest $request, DirectMessageGroup $dmGroup): JsonResponse
    {
        $user = $request->user();

        $message = $dmGroup->messages()->create([
            'user_id' => $user->id,
            'content' => $request->validated('content'),
        ]);

        $dmGroup->update(['last_message_at' => now()]);

        $message->load('user');

        $dmGroup->loadMissing('participants');

        foreach ($dmGroup->participants as $participant) {
            cache()->forget("user.{$participant->id}.dm_groups");
        }

        broadcast(new DirectMessageSent($message))->toOthers();

        $recipients = $dmGroup->participants->where('id', '!=', $user->id);

        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new DirectMessageNotification($message)
            );
        }

        return $this->createdResponse(
            new DirectMessageResource($message),
            'Created successfully',
            route('api.direct-messages.show', $dmGroup),
        );
    }

    /**
     * Update a DM message.
     */
    public function update(UpdateDirectMessageRequest $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if ($message->user_id !== $user->id) {
            return $this->forbiddenResponse('You can only edit your own messages.');
        }

        $message->update([
            'content' => $request->validated('content'),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new DirectMessageEdited($message))->toOthers();

        return $this->successResponse(
            new DirectMessageResource($message),
            'Message updated successfully',
        );
    }

    /**
     * Delete a DM message.
     */
    public function destroy(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse|Response
    {
        $user = $request->user();

        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            return $this->forbiddenResponse();
        }

        if ($message->direct_message_group_id !== $dmGroup->id) {
            return $this->notFoundResponse('Message not found in this conversation.');
        }

        if ($message->user_id !== $user->id) {
            return $this->forbiddenResponse('You can only delete your own messages.');
        }

        $messageId = $message->id;
        $message->delete();

        broadcast(new DirectMessageDeleted($messageId, $dmGroup->id))->toOthers();

        return $this->noContentResponse();
    }

    /**
     * Find an existing DM group with a specific user.
     *
     * GET /direct-messages/find?user_id=X
     */
    public function findDm(FindDirectMessageGroupRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $otherUserId = $request->validated('user_id');

        $existingDm = DirectMessageGroup::whereHas('participants', fn ($q) => $q->where('users.id', $currentUser->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $otherUserId))
            ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('users.id', [$currentUser->id, $otherUserId]))
            ->first();

        if (! $existingDm) {
            return $this->notFoundResponse('No existing DM group found.');
        }

        return $this->successResponse(['dm_group_id' => $existingDm->id]);
    }

    /**
     * Create a new DM group with a user.
     *
     * POST /direct-messages
     */
    public function createDm(CreateDirectMessageGroupRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $otherUserId = $request->validated('user_id');

        if ($currentUser->id === (int) $otherUserId) {
            return $this->validationErrorResponse('Cannot start a conversation with yourself.');
        }

        $existingDm = DirectMessageGroup::whereHas('participants', fn ($q) => $q->where('users.id', $currentUser->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $otherUserId))
            ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('users.id', [$currentUser->id, $otherUserId]))
            ->first();

        if ($existingDm) {
            return $this->successResponse(['dm_group_id' => $existingDm->id]);
        }

        $dmGroup = DirectMessageGroup::create([
            'owner_id' => $currentUser->id,
        ]);

        $dmGroup->participants()->attach([$currentUser->id, $otherUserId]);

        return $this->createdResponse(
            ['dm_group_id' => $dmGroup->id],
            'Created successfully',
            route('api.direct-messages.show', $dmGroup),
        );
    }
}
