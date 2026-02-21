import { usePage } from '@inertiajs/vue3';
import type { PresenceChannel } from 'laravel-echo';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import echo from '@/lib/echo';
import type { OnlineUser, UserStatusType } from '@/types';

export const usePresenceStore = defineStore('presence', () => {
    // Users currently connected via WebSocket
    const onlineUsers = ref<OnlineUser[]>([]);
    const isConnected = ref(false);
    let presenceChannel: PresenceChannel | null = null;

    // All members merged with live presence data.
    // Server-loaded members come from Inertia shared props;
    // WebSocket-connected users get their live status, everyone else is offline.
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

        presenceChannel = echo.join('online') as PresenceChannel;

        presenceChannel
            .here((users: OnlineUser[]) => {
                onlineUsers.value = users.map((u) => ({
                    ...u,
                    status: u.status || 'online',
                }));
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
    };

    const disconnect = () => {
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
