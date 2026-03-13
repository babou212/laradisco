<?php

namespace App\Services;

use App\Models\E2eeAuditLog;
use Illuminate\Support\Facades\DB;

class E2eeAuditService
{
    /**
     * Append an entry to the audit log with hash chaining.
     * Uses a transaction with a lock to prevent race conditions and chain forks.
     */
    public function logEvent(
        int $userId,
        string $eventType,
        ?string $deviceId = null,
        ?string $publicKey = null,
        ?string $signature = null,
        ?array $metadata = null,
    ): E2eeAuditLog {
        return DB::transaction(function () use ($userId, $eventType, $deviceId, $publicKey, $signature, $metadata) {
            $previousEntry = E2eeAuditLog::where('user_id', $userId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $previousHash = $previousEntry?->entry_hash;

            $timestamp = now();

            $entryHash = hash('sha256', implode('|', [
                $eventType,
                (string) $userId,
                (string) $deviceId,
                (string) $publicKey,
                $timestamp->toISOString(),
                (string) $previousHash,
            ]));

            return E2eeAuditLog::create([
                'user_id' => $userId,
                'event_type' => $eventType,
                'device_id' => $deviceId,
                'public_key' => $publicKey,
                'signature' => $signature,
                'metadata' => $metadata,
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
                'created_at' => $timestamp,
            ]);
        });
    }
}
