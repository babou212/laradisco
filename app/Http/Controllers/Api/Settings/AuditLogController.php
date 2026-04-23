<?php

namespace App\Http\Controllers\Api\Settings;

use App\Concerns\ApiResponse;
use App\Enums\ModerationAction;
use App\Http\Controllers\Controller;
use App\Models\ModerationAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * List moderation audit log entries with optional filters.
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

        $query = ModerationAuditLog::with(['actor:id,name,username', 'targetUser:id,name,username'])
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
