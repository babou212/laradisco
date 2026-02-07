import type { PresenceChannel } from 'laravel-echo';
import { onBeforeUnmount, onMounted } from 'vue';
import echo from '@/lib/echo';

/**
 * Composable for subscribing to a channel presence
 */
export function useChannelPresence(channelId: number) {
    let channel: PresenceChannel;

    const joinChannel = () => {
        channel = echo.join(`channel.${channelId}`) as PresenceChannel;

        return {
            channel,
            onHere: (callback: (users: any[]) => void) => {
                channel.here(callback);
                return channel;
            },
            onJoining: (callback: (user: any) => void) => {
                channel.joining(callback);
                return channel;
            },
            onLeaving: (callback: (user: any) => void) => {
                channel.leaving(callback);
                return channel;
            },
            onMessage: (callback: (data: any) => void) => {
                channel.listen('MessageSent', callback);
                return channel;
            },
            onTyping: (callback: (data: any) => void) => {
                channel.listen('UserTyping', callback);
                return channel;
            },
        };
    };

    onMounted(() => {
        joinChannel();
    });

    onBeforeUnmount(() => {
        if (channel) {
            echo.leave(`channel.${channelId}`);
        }
    });

    return { joinChannel };
}

/**
 * Composable for subscribing to global online presence
 */
export function useOnlinePresence() {
    let channel: PresenceChannel;

    const joinPresence = () => {
        channel = echo.join('online') as PresenceChannel;

        return {
            channel,
            onHere: (callback: (users: any[]) => void) => {
                channel.here(callback);
                return channel;
            },
            onJoining: (callback: (user: any) => void) => {
                channel.joining(callback);
                return channel;
            },
            onLeaving: (callback: (user: any) => void) => {
                channel.leaving(callback);
                return channel;
            },
            onPresenceUpdated: (callback: (data: any) => void) => {
                channel.listen('UserPresenceUpdated', callback);
                return channel;
            },
        };
    };

    onMounted(() => {
        joinPresence();
    });

    onBeforeUnmount(() => {
        if (channel) {
            echo.leave('online');
        }
    });

    return { joinPresence };
}

/**
 * Composable for subscribing to direct message groups
 */
export function useDirectMessagePresence(groupId: number) {
    let channel: PresenceChannel;

    const joinDM = () => {
        channel = echo.join(`dm.${groupId}`) as PresenceChannel;

        return {
            channel,
            onHere: (callback: (users: any[]) => void) => {
                channel.here(callback);
                return channel;
            },
            onJoining: (callback: (user: any) => void) => {
                channel.joining(callback);
                return channel;
            },
            onLeaving: (callback: (user: any) => void) => {
                channel.leaving(callback);
                return channel;
            },
            onMessage: (callback: (data: any) => void) => {
                channel.listen('MessageSent', callback);
                return channel;
            },
            onTyping: (callback: (data: any) => void) => {
                channel.listen('UserTyping', callback);
                return channel;
            },
        };
    };

    onMounted(() => {
        joinDM();
    });

    onBeforeUnmount(() => {
        if (channel) {
            echo.leave(`dm.${groupId}`);
        }
    });

    return { joinDM };
}
