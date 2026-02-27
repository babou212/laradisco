import { usePage } from '@inertiajs/vue3';
import type { PresenceChannel } from 'laravel-echo';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import echo from '@/lib/echo';
import type { OnlineUser, UserStatusType } from '@/types';

const HEARTBEAT_INTERVAL_MS = 60_000; // 60 seconds
const SYNC_INTERVAL_MS = 120_000; // 2 minutes — periodic full re-sync

export const usePresenceStore = defineStore('presence', () => {
    // Users currently known to be online (from API + WebSocket updates)
    const onlineUsers = ref<OnlineUser[]>([]);
    const isConnected = ref(false);
    let presenceChannel: PresenceChannel | null = null;
    let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
    let syncTimer: ReturnType<typeof setInterval> | null = null;

    // All members merged with live presence data.
    // Server-loaded members come from Inertia shared props;
    const allMembers = computed<OnlineUser[]>(() => {
        const page = usePage();
        const serverMembers = (page.props.members ?? []) as OnlineUser[];

        return serverMembers.map((member) => {
            const liveUser = onlineUsers.value.find((u) => u.id === member.id);

            if (liveUser) {
                return {
                    ...member,
                    status: liveUser.status || 'online',
                    custom_status:
                        liveUser.custom_status ?? member.custom_status,
                };
            }

            return { ...member, status: 'offline' as UserStatusType };
        });
    });

    /**
     * Fetch the authoritative online-users list from the Redis-backed API.
     * This works across all Reverb pods, unlike the per-instance .here() list.
     */
    const fetchOnlineUsers = async () => {
        try {
            const response = await fetch('/api/presence', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) return;

            const users: OnlineUser[] = await response.json();
            onlineUsers.value = users.map((u) => ({
                ...u,
                status: u.status || 'online',
            }));
        } catch {
            // Silently fail — WebSocket events will still provide updates
        }
    };

    /**
     * Send a heartbeat to keep this user's Redis presence entry alive.
     */
    const sendHeartbeat = async () => {
        try {
            await fetch('/presence/heartbeat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
            });
        } catch {
            // Heartbeat failure is non-critical
        }
    };

    const startTimers = () => {
        stopTimers();
        heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
        syncTimer = setInterval(fetchOnlineUsers, SYNC_INTERVAL_MS);
    };

    const stopTimers = () => {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
        if (syncTimer) {
            clearInterval(syncTimer);
            syncTimer = null;
        }
    };

    const connect = () => {
        // If already connected and the channel is still alive, skip
        if (presenceChannel && isConnected.value) {
            return;
        }

        // Clean up any stale channel reference before reconnecting
        if (presenceChannel) {
            try {
                echo.leave('online');
            } catch {
                // Channel may already be gone
            }
            presenceChannel = null;
            isConnected.value = false;
        }

        fetchOnlineUsers();

        presenceChannel = echo.join('online') as PresenceChannel;

        presenceChannel
            .here((users: OnlineUser[]) => {
                const apiUserIds = new Set(onlineUsers.value.map((u) => u.id));
                const merged = [...onlineUsers.value];

                for (const user of users) {
                    if (!apiUserIds.has(user.id)) {
                        merged.push({
                            ...user,
                            status: user.status || 'online',
                        });
                    }
                }

                onlineUsers.value = merged;
                isConnected.value = true;
            })
            .joining((user: OnlineUser) => {
                const exists = onlineUsers.value.find((u) => u.id === user.id);
                if (!exists) {
                    onlineUsers.value.push({
                        ...user,
                        status: user.status || 'online',
                    });
                }
            })
            .leaving((user: OnlineUser) => {
                onlineUsers.value = onlineUsers.value.filter(
                    (u) => u.id !== user.id,
                );
            })
            .listen('.user.presence.updated', (data: any) => {
                const user = onlineUsers.value.find(
                    (u) => u.id === data.user_id,
                );
                if (user) {
                    user.status = data.status;
                    user.custom_status = data.custom_status;
                }
            });

        startTimers();
    };

    const disconnect = () => {
        stopTimers();

        if (presenceChannel) {
            echo.leave('online');
            presenceChannel = null;
            onlineUsers.value = [];
            isConnected.value = false;
        }
    };

    const isUserOnline = (userId: number): boolean => {
        return onlineUsers.value.some((u) => u.id === userId);
    };

    const getUserStatus = (userId: number): OnlineUser | undefined => {
        const member = allMembers.value.find((u) => u.id === userId);
        return member;
    };

    const updateUserStatus = (
        userId: number,
        status: UserStatusType,
        customStatus: string | null = null,
    ) => {
        const user = onlineUsers.value.find((u) => u.id === userId);
        if (user) {
            user.status = status;
            user.custom_status = customStatus;
        }
    };

    return {
        onlineUsers,
        allMembers,
        isConnected,
        connect,
        disconnect,
        isUserOnline,
        getUserStatus,
        updateUserStatus,
    };
});
