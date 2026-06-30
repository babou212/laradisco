<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Http\Controllers\Controller;
use App\Models\ModerationAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Settings: Audit Log
 */
class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * List moderation audit log entries with optional filters.
     *
     * @queryParam action string Filter by moderation action type. Example: ban
     * @queryParam actor_id integer Filter by the acting user's id. Example: 3
     * @queryParam target_user_id integer Filter by the targeted user's id. Example: 7
     * @queryParam per_page integer Page size, 1–100. Defaults to 25. Example: 25
     *
     * @response 200 {"current_page":1,"data":[{"id":1,"actor_id":3,"action":"ban","target_user_id":7,"metadata":{"target_username":"bob"},"created_at":"2026-06-30T12:00:00.000000Z","actor":{"id":3,"username":"alice"},"target_user":{"id":7,"username":"bob"}}],"per_page":25}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('view_audit_log') && ! $user->isAdministrator()) {
            return $this->forbiddenResponse('You do not have permission to view the audit log.');
        }

        $request->validate([
            'action' => ['nullable', 'string', Rule::in(array_column(ModerationAction::cases(), 'value'))],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ModerationAuditLog::with(['actor:id,username', 'targetUser:id,username'])
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->input('actor_id'));
        }

        if ($request->filled('target_user_id')) {
            $query->where('target_user_id', $request->input('target_user_id'));
        }

        $perPage = $request->integer('per_page', 25);

        return response()->json($query->simplePaginate($perPage));
    }
}
