<?php

namespace App\Services;

use App\Enums\UserStatusType;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class PresenceService
{
    private const HASH_KEY = 'presence:online';

    /**
     * The client sends a heartbeat every 30s. We allow a grace window before
     * downgrading presence so a single delayed/jittery beat does not flap the
     * user to idle. A user with no heartbeat for IDLE_TTL seconds is "idle";
     * one with no heartbeat for OFFLINE_TTL seconds is removed and "offline".
     */
    private const IDLE_TTL = 45; // seconds (~1.5 missed heartbeats)

    private const OFFLINE_TTL = 105; // seconds (idle + 60)

    /**
     * Register a user as online in Redis.
     */
    public function register(User $user): void
    {
        $base = $this->normalizeBase($user->status ?? 'online');

        // Appear-offline users are never advertised in the registry.
        if ($base === 'offline') {
            $this->unregister($user);

            return;
        }

        Redis::hSet(self::HASH_KEY, (string) $user->id, json_encode([
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_urls' => $user->avatar_urls,
            'base_status' => $base,
            'status' => $base,
            'custom_status' => $user->custom_status,
            'last_heartbeat' => now()->timestamp,
        ]));
    }

    /**
     * Refresh a user's heartbeat timestamp and bring them back online if a
     * previous lapse had downgraded them to idle/offline.
     *
     * @return UserStatusType|null the new effective status when it changed and
     *                             should be broadcast, otherwise null
     */
    public function heartbeat(User $user): ?UserStatusType
    {
        $now = now()->timestamp;
        $existing = Redis::hGet(self::HASH_KEY, (string) $user->id);

        // No live entry — the user was swept offline (or never registered).
        // Re-register from their chosen status and announce the transition,
        // unless they have chosen to appear offline.
        if (! $existing) {
            $base = $this->normalizeBase($user->status ?? 'online');

            if ($base === 'offline') {
                return null;
            }

            $this->register($user);

            return UserStatusType::from($base);
        }

        $data = json_decode($existing, true);
        $base = $this->normalizeBase($data['base_status'] ?? $data['status'] ?? 'online');
        $previous = $data['status'] ?? $base;

        $data['last_heartbeat'] = $now;
        $effective = $this->deriveStatus($base, $now, $now); // fresh beat -> online (or dnd)
        $data['status'] = $effective;

        Redis::hSet(self::HASH_KEY, (string) $user->id, json_encode($data));

        return $effective !== $previous ? UserStatusType::from($effective) : null;
    }

    /**
     * Update a user's chosen (base) status in Redis.
     */
    public function updateStatus(User $user, string $status, ?string $customStatus = null): void
    {
        $existing = Redis::hGet(self::HASH_KEY, (string) $user->id);

        if ($existing) {
            $data = json_decode($existing, true);
            $data['base_status'] = $status;
            $data['status'] = $status;
            $data['custom_status'] = $customStatus;
            $data['last_heartbeat'] = now()->timestamp;
            Redis::hSet(self::HASH_KEY, (string) $user->id, json_encode($data));
        } else {
            $user->refresh();
            $this->register($user);
        }
    }

    /**
     * Remove a user from the online registry.
     */
    public function unregister(User $user): void
    {
        Redis::hDel(self::HASH_KEY, (string) $user->id);
    }

    /**
     * Get all currently online users, computing their effective status from
     * heartbeat freshness and cleaning up entries that have gone offline.
     *
     * @return array<int, array{id: int, username: string, display_name: string, avatar_urls: array<string, mixed>|null, status: string, custom_status: string|null}>
     */
    public function getOnlineUsers(): array
    {
        $all = Redis::hGetAll(self::HASH_KEY);

        if (empty($all)) {
            return [];
        }

        $now = now()->timestamp;
        $online = [];
        $stale = [];

        foreach ($all as $userId => $json) {
            $data = json_decode($json, true);

            if (! $data) {
                $stale[] = $userId;

                continue;
            }

            $base = $this->normalizeBase($data['base_status'] ?? $data['status'] ?? 'online');
            $effective = $this->deriveStatus($base, (int) $data['last_heartbeat'], $now);

            if ($effective === 'offline') {
                $stale[] = $userId;

                continue;
            }

            $data['status'] = $effective;
            unset($data['last_heartbeat'], $data['base_status']);
            $online[] = $data;
        }

        // Lazy cleanup of entries that have aged out to offline.
        if (! empty($stale)) {
            Redis::hDel(self::HASH_KEY, ...$stale);
        }

        return $online;
    }

    /**
     * Recompute every entry's effective status from heartbeat freshness,
     * persist any changes, drop newly-offline users, and return the
     * transitions other clients still need to be told about.
     *
     * @return array<int, array{id: int, username: string, display_name: string, avatar_urls: array<string, mixed>|null, status: string, custom_status: string|null}>
     */
    public function sweep(): array
    {
        $all = Redis::hGetAll(self::HASH_KEY);

        if (empty($all)) {
            return [];
        }

        $now = now()->timestamp;
        $transitions = [];
        $remove = [];

        foreach ($all as $userId => $json) {
            $data = json_decode($json, true);

            if (! $data) {
                $remove[] = $userId;

                continue;
            }

            $base = $this->normalizeBase($data['base_status'] ?? $data['status'] ?? 'online');
            $previous = $data['status'] ?? $base;
            $effective = $this->deriveStatus($base, (int) $data['last_heartbeat'], $now);

            if ($effective === $previous) {
                continue;
            }

            if ($effective === 'offline') {
                $remove[] = $userId;
            } else {
                $data['status'] = $effective;
                Redis::hSet(self::HASH_KEY, (string) $userId, json_encode($data));
            }

            unset($data['last_heartbeat'], $data['base_status']);
            $data['status'] = $effective;
            $transitions[] = $data;
        }

        if (! empty($remove)) {
            Redis::hDel(self::HASH_KEY, ...$remove);
        }

        return $transitions;
    }

    /**
     * Collapse a stored/chosen status into one of the base statuses that
     * heartbeat freshness is allowed to modulate. Anything that is not an
     * explicit manual choice (dnd / offline) is treated as the online family.
     */
    private function normalizeBase(?string $base): string
    {
        return in_array($base, ['dnd', 'offline'], true) ? $base : 'online';
    }

    /**
     * Derive the effective broadcast status from the user's base status and
     * how long ago their last heartbeat arrived.
     */
    private function deriveStatus(string $base, int $lastHeartbeat, int $now): string
    {
        if ($base === 'offline') {
            return 'offline';
        }

        $age = $now - $lastHeartbeat;

        // A do-not-disturb user stays dnd until the connection is truly gone;
        // we don't surface a transient "idle" for them.
        if ($base === 'dnd') {
            return $age >= self::OFFLINE_TTL ? 'offline' : 'dnd';
        }

        if ($age >= self::OFFLINE_TTL) {
            return 'offline';
        }

        if ($age >= self::IDLE_TTL) {
            return 'idle';
        }

        return 'online';
    }
}
