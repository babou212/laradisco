import { usePage } from '@inertiajs/vue3';
import type { Channel } from 'laravel-echo';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import echo from '@/lib/echo';
import type { OnlineUser, UserStatusType } from '@/types';

const HEARTBEAT_INTERVAL_MS = 60_000;
const SYNC_INTERVAL_MS = 120_000;

export const usePresenceStore = defineStore('presence', () => {
    const onlineUsers = ref<OnlineUser[]>([]);
    let channel: Channel | null = null;
    let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
    let syncTimer: ReturnType<typeof setInterval> | null = null;

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
            // Will retry on next sync interval
        }
    };

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

    /**
     * Apply an incremental presence update from a broadcast event.
     */
    const applyPresenceUpdate = (data: any) => {
        const idx = onlineUsers.value.findIndex((u) => u.id === data.user_id);

        if (data.status === 'offline') {
            if (idx !== -1) {
                onlineUsers.value.splice(idx, 1);
            }
        } else if (idx !== -1) {
            onlineUsers.value[idx].status = data.status;
            onlineUsers.value[idx].custom_status = data.custom_status;
        } else {
            onlineUsers.value.push({
                id: data.user_id,
                username: data.username,
                display_name: data.display_name ?? data.username,
                avatar_path: data.avatar_path ?? null,
                status: data.status,
                custom_status: data.custom_status,
            });
        }
    };

    const connect = async () => {
        if (channel) return;

        await fetchOnlineUsers();

        channel = echo.private('presence');

        channel.listen('.user.presence.updated', applyPresenceUpdate);

        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon('/presence/offline');
        });

        if (heartbeatTimer) clearInterval(heartbeatTimer);
        if (syncTimer) clearInterval(syncTimer);
        heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
        syncTimer = setInterval(fetchOnlineUsers, SYNC_INTERVAL_MS);
    };

    const getUserStatus = (userId: number): OnlineUser | undefined => {
        return allMembers.value.find((u) => u.id === userId);
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
        connect,
        getUserStatus,
        updateUserStatus,
    };
});
