import type { PresenceChannel } from 'laravel-echo';
import { ref } from 'vue';
import echo from '@/lib/echo';
import type { OnlineUser } from '@/types';

// Global reactive state for online users
const onlineUsers = ref<OnlineUser[]>([]);
const isConnected = ref(false);
let presenceChannel: PresenceChannel | null = null;

/**
 * Global presence composable - tracks all online users application-wide.
 * Should be initialized once in the app root component.
 * Other components can import { onlineUsers } to reactively access online users.
 */
export function useGlobalPresence() {
    const connect = () => {
        if (presenceChannel) {
            return; // Already connected
        }

        presenceChannel = echo.join('online') as PresenceChannel;

        presenceChannel
            .here((users: OnlineUser[]) => {
                // Set all users to 'online' since they're in the presence channel
                onlineUsers.value = users.map((u) => ({ ...u, status: 'online' }));
                isConnected.value = true;
            })
            .joining((user: OnlineUser) => {
                // Add user if not already in the list
                const exists = onlineUsers.value.find((u) => u.id === user.id);
                if (!exists) {
                    onlineUsers.value.push({ ...user, status: 'online' });
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

    const getUserStatus = (userId: number) => {
        return onlineUsers.value.find((u) => u.id === userId);
    };

    return {
        onlineUsers,
        isConnected,
        connect,
        disconnect,
        isUserOnline,
        getUserStatus,
    };
}
