<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AckInboxRequest;
use App\Models\InboxMessage;
use App\Services\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly InboxService $inboxService) {}

    /**
     * Pending inbox messages for the authenticated user (oldest first).
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->inboxService->pendingForUser($request->user());

        return response()->json([
            'data' => $items->map(fn (InboxMessage $row) => [
                'id' => $row->id,
                'message_type' => $row->message_type,
                'message_id' => $row->message_id,
                'payload' => $row->payload,
                'created_at' => $row->created_at?->toISOString(),
            ])->all(),
        ]);
    }

    /**
     * Acknowledge (and delete) delivered inbox messages. Idempotent.
     */
    public function ack(AckInboxRequest $request): JsonResponse
    {
        $deleted = $this->inboxService->ack(
            $request->user(),
            $request->validated('items'),
        );

        return response()->json([
            'data' => ['deleted' => $deleted],
        ]);
    }
}
