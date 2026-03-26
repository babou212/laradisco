<?php

namespace App\Services;

use App\Models\UserKeyBackup;
use Illuminate\Support\Carbon;

class KeyBackupService
{
    /**
     * Check if the backup is currently locked. Returns lock details or null if unlocked.
     *
     * @return array{locked: true, unlocks_at: string, remaining_minutes: int}|null
     */
    public function getLockStatus(UserKeyBackup $backup): ?array
    {
        if (! $backup->isLocked()) {
            return null;
        }

        /** @var Carbon $lockedAt */
        $lockedAt = $backup->locked_at;
        $unlocksAt = $lockedAt->copy()->addMinutes(UserKeyBackup::LOCKOUT_DURATION_MINUTES);

        return [
            'locked' => true,
            'unlocks_at' => $unlocksAt->toIso8601String(),
            'remaining_minutes' => (int) now()->diffInMinutes($unlocksAt, false),
        ];
    }

    /**
     * Record a retrieval attempt. Returns lock details if the backup becomes locked, null otherwise.
     *
     * @return array{locked: true, unlocks_at: string, remaining_minutes: int}|null
     */
    public function recordAttempt(UserKeyBackup $backup): ?array
    {
        $backup->increment('failed_attempt_count');
        $backup->last_attempt_at = now();

        if ($backup->failed_attempt_count >= UserKeyBackup::MAX_FAILED_ATTEMPTS) {
            $backup->locked_at = now();
            $backup->save();

            /** @var Carbon $newLockedAt */
            $newLockedAt = $backup->locked_at;

            return [
                'locked' => true,
                'unlocks_at' => $newLockedAt->copy()->addMinutes(UserKeyBackup::LOCKOUT_DURATION_MINUTES)->toIso8601String(),
                'remaining_minutes' => UserKeyBackup::LOCKOUT_DURATION_MINUTES,
            ];
        }

        $backup->save();

        return null;
    }

    /**
     * Reset the attempt counter (after successful restore or 2FA unlock).
     */
    public function resetAttempts(UserKeyBackup $backup): void
    {
        $backup->resetAttempts();
    }
}
