import type { PresenceChannel } from 'laravel-echo';
import { defineStore } from 'pinia';
import { ref } from 'vue';
import echo from '@/lib/echo';
import type { OnlineUser, UserStatusType } from '@/types';

export const usePresenceStore = defineStore('presence', () => {
    const onlineUsers = ref<OnlineUser[]>([]);
    const isConnected = ref(false);
    let presenceChannel: PresenceChannel | null = null;

    const connect = () => {
        if (presenceChannel) {
            return; // Already connected
        }

        presenceChannel = echo.join('online') as PresenceChannel;

        presenceChannel
            .here((users: OnlineUser[]) => {
                // Preserve existing status or default to 'online'
                onlineUsers.value = users.map((u) => ({
                    ...u,
                    status: u.status || 'online',
                }));
                isConnected.value = true;
            })
            .joining((user: OnlineUser) => {
                // Add user if not already in the list
                const exists = onlineUsers.value.find((u) => u.id === user.id);
                if (!exists) {
                    onlineUsers.value.push({
                        ...user,
                        status: user.status || 'online',
                    });
                }
            })
            .leaving((user: OnlineUser) => {
                // Remove user from list
                onlineUsers.value = onlineUsers.value.filter(
                    (u) => u.id !== user.id,
                );
            })
            .listen('UserPresenceUpdated', (data: any) => {
                // Update user's status and custom status in the list
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
        return onlineUsers.value.find((u) => u.id === userId);
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
        isConnected,
        connect,
        disconnect,
        isUserOnline,
        getUserStatus,
        updateUserStatus,
    };
});
