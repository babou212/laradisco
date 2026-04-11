<?php

namespace App\Services;

use App\Enums\ModerationAction;
use App\Models\ModerationAuditLog;

class ModerationAuditService
{
    public function log(
        int $actorId,
        ModerationAction $action,
        ?int $targetUserId = null,
        ?int $targetResourceId = null,
        ?string $targetResourceType = null,
        ?array $metadata = null,
    ): ModerationAuditLog {
        return ModerationAuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'target_resource_id' => $targetResourceId,
            'target_resource_type' => $targetResourceType,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
