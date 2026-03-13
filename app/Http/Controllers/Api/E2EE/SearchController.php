<?php

namespace App\Http\Controllers\Api\E2EE;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\E2EE\SearchRequest;
use App\Models\Channel;
use App\Models\DirectMessageGroup;
use App\Models\EncryptedSearchToken;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Search encrypted messages by matching trapdoor tokens.
     *
     * Accepts one or more HMAC-SHA-256 tokens generated client-side.
     * Returns message IDs that match ALL tokens (AND semantics).
     * The client then fetches and decrypts those messages to display results.
     */
    public function search(SearchRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $conversationType = $validated['conversation_type'];
        $conversationId = $validated['conversation_id'];
        $tokens = $validated['tokens'];
        $limit = $validated['limit'] ?? 50;
        $beforeId = $validated['before_id'] ?? null;

        if ($conversationType === 'channel') {
            $channel = Channel::find($conversationId);
            if (! $channel || ! $this->permissionService->userCanViewChannel($user, $channel)) {
                return $this->forbiddenResponse('You do not have access to this channel.');
            }
        } elseif ($conversationType === 'dm') {
            $dmGroup = DirectMessageGroup::find($conversationId);
            if (! $dmGroup || ! $dmGroup->participants()->where('users.id', $user->id)->exists()) {
                return $this->forbiddenResponse('You do not have access to this conversation.');
            }
        }

        $query = EncryptedSearchToken::forConversation($conversationType, $conversationId)
            ->matchingTokens($tokens)
            ->select('message_id')
            ->groupBy('message_id');

        if (count($tokens) > 1) {
            $query->havingRaw('COUNT(DISTINCT token) = ?', [count($tokens)]);
        }

        if ($beforeId) {
            $query->where('message_id', '<', $beforeId);
        }

        $messageIds = $query->orderByDesc('message_id')
            ->limit($limit + 1)
            ->pluck('message_id');

        $hasMore = $messageIds->count() > $limit;
        if ($hasMore) {
            $messageIds = $messageIds->take($limit);
        }

        return $this->successResponse([
            'message_ids' => $messageIds->values(),
            'has_more' => $hasMore,
        ]);
    }
}
