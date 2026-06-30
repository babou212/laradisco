<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AckInboxRequest;
use App\Models\InboxMessage;
use App\Services\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Inbox
 */
class InboxController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly InboxService $inboxService) {}

    /**
     * List pending inbox messages
     *
     * Pending inbox messages for the authenticated user (oldest first). These
     * are durable delivery rows drained on reconnect and removed via `ack`.
     *
     * @response 200 {"data": [{"id": 91, "message_type": "direct_message", "message_id": 560, "payload": {"id": 560, "content": "hello world"}, "created_at": "2026-06-30T12:05:00.000000Z"}]}
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
     * Acknowledge inbox messages
     *
     * Acknowledge (and delete) delivered inbox messages. Idempotent. Returns the
     * number of rows deleted.
     *
     * @response 200 {"data": {"deleted": 1}}
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
