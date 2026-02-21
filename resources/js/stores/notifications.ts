import { usePage } from '@inertiajs/vue3';
import { defineStore } from 'pinia';
import { ref } from 'vue';
import echo from '@/lib/echo';

export interface AppNotification {
    id: string;
    type: string;
    data: {
        message_id: number;
        sender_id: number;
        sender_username: string;
        sender_avatar: string | null;
        content: string;
        // Mention notification fields
        channel_id?: number;
        channel_name?: string;
        mention_type?: 'user' | 'everyone' | 'here';
        // DM notification fields
        dm_group_id?: number;
        dm_group_name?: string | null;
        notification_type?: 'direct_message';
    };
    read_at: string | null;
    created_at: string;
}

export interface ToastNotification extends AppNotification {
    dismissing?: boolean;
}

export interface NotificationPreferences {
    enable_toast_notifications: boolean;
    enable_browser_notifications: boolean;
    enable_dm_notifications: boolean;
    enable_mention_notifications: boolean;
}

const defaultPreferences: NotificationPreferences = {
    enable_toast_notifications: true,
    enable_browser_notifications: true,
    enable_dm_notifications: true,
    enable_mention_notifications: true,
};

const getPreferences = (): NotificationPreferences => {
    const page = usePage();
    const user = page.props.auth?.user as Record<string, unknown> | undefined;
    return {
        ...defaultPreferences,
        ...(user?.notification_preferences as
            | Partial<NotificationPreferences>
            | undefined),
    };
};

export const useNotificationStore = defineStore('notifications', () => {
    const notifications = ref<AppNotification[]>([]);
    const unreadCount = ref(0);
    const toasts = ref<ToastNotification[]>([]);
    const isConnected = ref(false);

    let userId: number | null = null;

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/api/notifications', {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                const data = await response.json();
                notifications.value = data.notifications;
                unreadCount.value = data.unread_count;
            }
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
        }
    };

    const connect = (currentUserId: number) => {
        if (isConnected.value && userId === currentUserId) return;

        userId = currentUserId;

        echo.private(`App.Models.User.${currentUserId}`).notification(
            (raw: Record<string, unknown>) => {
                const notification: AppNotification = {
                    id: raw.id as string,
                    type:
                        typeof raw.type === 'string'
                            ? raw.type.split('\\').pop()!
                            : String(raw.type),
                    data: {
                        message_id: raw.message_id as number,
                        sender_id: raw.sender_id as number,
                        sender_username: raw.sender_username as string,
                        sender_avatar:
                            (raw.sender_avatar as string | null) ?? null,
                        content: raw.content as string,
                        // Mention fields
                        channel_id: raw.channel_id as number | undefined,
                        channel_name: raw.channel_name as string | undefined,
                        mention_type: raw.mention_type as
                            | 'user'
                            | 'everyone'
                            | 'here'
                            | undefined,
                        // DM fields
                        dm_group_id: raw.dm_group_id as number | undefined,
                        dm_group_name: raw.dm_group_name as
                            | string
                            | null
                            | undefined,
                        notification_type: raw.notification_type as
                            | 'direct_message'
                            | undefined,
                    },
                    read_at: null,
                    created_at: new Date().toISOString(),
                };

                notifications.value.unshift(notification);
                unreadCount.value++;

                const prefs = getPreferences();
                const isDm =
                    notification.data.notification_type === 'direct_message';

                if (isDm && !prefs.enable_dm_notifications) return;
                if (!isDm && !prefs.enable_mention_notifications) return;

                if (document.hidden) {
                    if (prefs.enable_browser_notifications) {
                        showBrowserNotification(notification);
                    }
                } else {
                    if (prefs.enable_toast_notifications) {
                        addToast(notification);
                    }
                }
            },
        );

        isConnected.value = true;
        fetchNotifications();
        registerServiceWorker();
    };

    const addToast = (notification: AppNotification) => {
        const toast: ToastNotification = { ...notification, dismissing: false };
        toasts.value.push(toast);

        setTimeout(() => {
            dismissToast(notification.id);
        }, 5000);
    };

    const dismissToast = (notificationId: string) => {
        const index = toasts.value.findIndex((t) => t.id === notificationId);
        if (index !== -1) {
            toasts.value[index].dismissing = true;
            setTimeout(() => {
                toasts.value = toasts.value.filter(
                    (t) => t.id !== notificationId,
                );
            }, 300);
        }
    };

    const markAsRead = async (notificationId: string) => {
        try {
            const csrfMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const csrfToken = csrfMatch ? decodeURIComponent(csrfMatch[1]) : '';

            const response = await fetch(
                `/api/notifications/${notificationId}/read`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': csrfToken,
                    },
                },
            );

            if (response.ok) {
                const data = await response.json();
                notifications.value = notifications.value.filter(
                    (n) => n.id !== notificationId,
                );
                unreadCount.value = data.unread_count;
            }
        } catch (err) {
            console.error('Failed to mark notification as read:', err);
        }
    };

    const markAllAsRead = async () => {
        try {
            const csrfMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const csrfToken = csrfMatch ? decodeURIComponent(csrfMatch[1]) : '';

            const response = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': csrfToken,
                },
            });

            if (response.ok) {
                notifications.value = [];
                unreadCount.value = 0;
            }
        } catch (err) {
            console.error('Failed to mark all notifications as read:', err);
        }
    };

    const showBrowserNotification = (notification: AppNotification) => {
        if (!('Notification' in window)) return;

        if (Notification.permission === 'granted') {
            createBrowserNotification(notification);
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    createBrowserNotification(notification);
                }
            });
        }
    };

    const createBrowserNotification = (notification: AppNotification) => {
        const { data } = notification;
        let title: string;
        let navigateUrl: string;

        if (data.notification_type === 'direct_message') {
            title = `Message from ${data.sender_username}`;
            navigateUrl = `/direct-message/${data.dm_group_id}`;
        } else {
            const mentionLabel =
                data.mention_type === 'everyone'
                    ? '@everyone'
                    : data.mention_type === 'here'
                      ? '@here'
                      : `@${data.sender_username}`;
            title = `${mentionLabel} in #${data.channel_name}`;
            navigateUrl = `/?channel=${data.channel_id}`;
        }

        const body = `${data.sender_username}: ${data.content.substring(0, 100)}`;

        const browserNotif = new Notification(title, {
            body,
            icon: data.sender_avatar || '/favicon.ico',
            tag: `notification-${notification.id}`,
            requireInteraction: false,
        });

        browserNotif.onclick = () => {
            window.focus();
            window.location.href = navigateUrl;
            browserNotif.close();
        };
    };

    const requestPermission = () => {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    };

    const registerServiceWorker = async () => {
        if (!('serviceWorker' in navigator)) return;

        try {
            await navigator.serviceWorker.register('/sw-notifications.js', {
                scope: '/',
            });
        } catch (err) {
            console.error('Service worker registration failed:', err);
        }
    };

    const disconnect = () => {
        if (userId) {
            echo.leave(`App.Models.User.${userId}`);
            userId = null;
            isConnected.value = false;
        }
    };

    return {
        notifications,
        unreadCount,
        toasts,
        isConnected,
        connect,
        disconnect,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
        dismissToast,
        requestPermission,
    };
});
