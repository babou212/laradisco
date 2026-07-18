<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserKeyBackup extends Model
{
    /** Maximum failed retrieval attempts before lockout. */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** Lockout duration in minutes. */
    public const LOCKOUT_DURATION_MINUTES = 60;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'encrypted_bundle',
        'salt',
        'nonce',
        'argon2_params',
        'version',
        'failed_attempt_count',
        'locked_at',
        'last_attempt_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'argon2_params' => 'array',
            'version' => 'integer',
            'failed_attempt_count' => 'integer',
            'locked_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the backup is currently locked.
     */
    public function isLocked(): bool
    {
        /** @var Carbon|null $lockedAt */
        $lockedAt = $this->locked_at;

        if (! $lockedAt) {
            return false;
        }

        return $lockedAt->addMinutes(self::LOCKOUT_DURATION_MINUTES)->isFuture();
    }

    /**
     * Record a failed attempt and lock if threshold reached.
     */
    public function recordFailedAttempt(): void
    {
        $this->increment('failed_attempt_count');
        $this->last_attempt_at = now();

        if ($this->failed_attempt_count >= self::MAX_FAILED_ATTEMPTS) {
            $this->locked_at = now();
        }

        $this->save();
    }

    /**
     * Reset the failed attempt counter (called after successful restore).
     */
    public function resetAttempts(): void
    {
        $this->update([
            'failed_attempt_count' => 0,
            'locked_at' => null,
            'last_attempt_at' => null,
        ]);
    }
}
