<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageEdited;
use App\Events\DirectMessageSent;
use App\Models\DirectMessage;
use App\Models\DirectMessageGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DirectMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('DirectMessages', [
            'dmGroups' => $this->getCachedDmGroups($user),
        ]);
    }

    public function show(Request $request, DirectMessageGroup $dmGroup): Response
    {
        $user = $request->user();

        // Check if user is a participant
        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        $otherParticipant = $dmGroup->participants()->where('users.id', '!=', $user->id)->first();

        $dmGroups = $this->getCachedDmGroups($user);

        return Inertia::render('DirectMessages', [
            'dmGroups' => $dmGroups,
            'currentDmGroup' => [
                'id' => $dmGroup->id,
                'name' => $dmGroup->name ?? $otherParticipant?->username ?? 'Unknown',
                'other_user' => $otherParticipant ? [
                    'id' => $otherParticipant->id,
                    'username' => $otherParticipant->username,
                    'avatar_path' => $otherParticipant->avatar_path,
                ] : null,
            ],
            'messages' => Inertia::scroll(fn () => $dmGroup->messages()
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->cursorPaginate(50)
            ),
        ]);
    }

    public function store(Request $request, DirectMessageGroup $dmGroup): RedirectResponse
    {
        $user = $request->user();

        // Check if user is a participant
        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'reply_to_id' => 'nullable|exists:direct_messages,id',
        ]);

        $message = $dmGroup->messages()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        $dmGroup->update(['last_message_at' => now()]);

        $message->load('user');

        // Invalidate cache for all participants
        foreach ($dmGroup->participants as $participant) {
            cache()->forget("user.{$participant->id}.dm_groups");
        }

        broadcast(new DirectMessageSent($message))->toOthers();

        return back();
    }

    public function update(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        // Check if user is a participant
        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        // Check if user owns the message
        if ($message->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $message->update([
            'content' => $validated['content'],
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new DirectMessageEdited($message))->toOthers();

        return response()->json(['message' => $message]);
    }

    public function destroy(Request $request, DirectMessageGroup $dmGroup, DirectMessage $message): JsonResponse
    {
        $user = $request->user();

        // Check if user is a participant
        if (! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        // Check if user owns the message
        if ($message->user_id !== $user->id) {
            abort(403);
        }

        $messageId = $message->id;
        $message->delete();

        broadcast(new DirectMessageDeleted($messageId, $dmGroup->id))->toOthers();

        return response()->json(['success' => true]);
    }

    public function startOrGetDm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $currentUser = $request->user();
        $otherUserId = $validated['user_id'];

        if ($currentUser->id === $otherUserId) {
            return response()->json(['error' => 'Cannot start DM with yourself'], 400);
        }

        // Find existing DM between these two users
        $existingDm = DirectMessageGroup::whereHas('participants', function ($query) use ($currentUser) {
            $query->where('users.id', $currentUser->id);
        })
            ->whereHas('participants', function ($query) use ($otherUserId) {
                $query->where('users.id', $otherUserId);
            })
            ->whereDoesntHave('participants', function ($query) use ($currentUser, $otherUserId) {
                $query->whereNotIn('users.id', [$currentUser->id, $otherUserId]);
            })
            ->first();

        if ($existingDm) {
            return response()->json(['dm_group_id' => $existingDm->id]);
        }

        // Create new DM
        $dmGroup = DirectMessageGroup::create([
            'owner_id' => $currentUser->id,
        ]);

        $dmGroup->participants()->attach([$currentUser->id, $otherUserId]);

        return response()->json(['dm_group_id' => $dmGroup->id]);
    }

    /**
     * Get cached DM groups sidebar data for a user.
     */
    private function getCachedDmGroups(User $user): mixed
    {
        return cache()->remember(
            "user.{$user->id}.dm_groups",
            now()->addMinutes(5),
            function () use ($user) {
                return $user->directMessageGroups()
                    ->with(['participants', 'messages' => function ($query) {
                        $query->latest()->limit(1)->with('user');
                    }])
                    ->orderBy('last_message_at', 'desc')
                    ->get()
                    ->map(function ($group) use ($user) {
                        $otherParticipant = $group->participants->firstWhere('id', '!=', $user->id);
                        $lastMessage = $group->messages->first();

                        return [
                            'id' => $group->id,
                            'name' => $group->name ?? $otherParticipant?->username ?? 'Unknown',
                            'other_user' => $otherParticipant ? [
                                'id' => $otherParticipant->id,
                                'username' => $otherParticipant->username,
                                'avatar_path' => $otherParticipant->avatar_path,
                            ] : null,
                            'last_message' => $lastMessage ? [
                                'content' => $lastMessage->content,
                                'created_at' => $lastMessage->created_at,
                                'user_id' => $lastMessage->user_id,
                            ] : null,
                            'last_message_at' => $group->last_message_at,
                        ];
                    });
            }
        );
    }
}
