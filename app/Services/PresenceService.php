<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class PresenceService
{
    private const HASH_KEY = 'presence:online';

    private const HEARTBEAT_TTL = 120; // seconds

    /**
     * Register a user as online in Redis.
     */
    public function register(User $user): void
    {
        Redis::hSet(self::HASH_KEY, (string) $user->id, json_encode([
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_path' => $user->avatar_path,
            'status' => $user->status ?? 'online',
            'custom_status' => $user->custom_status,
            'last_heartbeat' => now()->timestamp,
        ]));
    }

    /**
     * Refresh a user's heartbeat timestamp.
     */
    public function heartbeat(User $user): void
    {
        $existing = Redis::hGet(self::HASH_KEY, (string) $user->id);

        if ($existing) {
            $data = json_decode($existing, true);
            $data['last_heartbeat'] = now()->timestamp;
            Redis::hSet(self::HASH_KEY, (string) $user->id, json_encode($data));
        } else {
            $this->register($user);
        }
    }

    /**
     * Update a user's status in Redis.
     */
    public function updateStatus(User $user, string $status, ?string $customStatus = null): void
    {
        $existing = Redis::hGet(self::HASH_KEY, (string) $user->id);

        if ($existing) {
            $data = json_decode($existing, true);
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
     * Get all currently online users, cleaning up stale entries.
     *
     * @return array<int, array{id: int, username: string, display_name: string, avatar_path: string|null, status: string, custom_status: string|null}>
     */
    public function getOnlineUsers(): array
    {
        $all = Redis::hGetAll(self::HASH_KEY);

        if (empty($all)) {
            return [];
        }

        $cutoff = now()->subSeconds(self::HEARTBEAT_TTL)->timestamp;
        $online = [];
        $stale = [];

        foreach ($all as $userId => $json) {
            $data = json_decode($json, true);

            if ($data && $data['last_heartbeat'] >= $cutoff) {
                unset($data['last_heartbeat']);
                $online[] = $data;
            } else {
                $stale[] = $userId;
            }
        }

        // Lazy cleanup of stale entries
        if (! empty($stale)) {
            Redis::hDel(self::HASH_KEY, ...$stale);
        }

        return $online;
    }

    /**
     * Sweep stale presence entries from Redis and return their user data.
     *
     * @return array<int, array{id: int, username: string, display_name: string, avatar_path: string|null, status: string, custom_status: string|null}>
     */
    public function sweepStale(): array
    {
        $all = Redis::hGetAll(self::HASH_KEY);

        if (empty($all)) {
            return [];
        }

        $cutoff = now()->subSeconds(self::HEARTBEAT_TTL)->timestamp;
        $staleUsers = [];
        $staleKeys = [];

        foreach ($all as $userId => $json) {
            $data = json_decode($json, true);

            if (! $data || $data['last_heartbeat'] < $cutoff) {
                $staleKeys[] = $userId;
                if ($data) {
                    unset($data['last_heartbeat']);
                    $staleUsers[] = $data;
                }
            }
        }

        if (! empty($staleKeys)) {
            Redis::hDel(self::HASH_KEY, ...$staleKeys);
        }

        return $staleUsers;
    }
}
