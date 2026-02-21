/**
 * Service Worker for Laradisco notifications.
 *
 * This service worker registers for push events and displays
 * native OS-level notifications when they arrive.
 */

self.addEventListener('push', function (event) {
    if (!event.data) return;

    try {
        const data = event.data.json();

        const mentionLabel =
            data.mention_type === 'everyone'
                ? '@everyone'
                : data.mention_type === 'here'
                  ? '@here'
                  : `@${data.sender_username}`;

        const title = `${mentionLabel} in #${data.channel_name}`;
        const options = {
            body: `${data.sender_username}: ${data.content?.substring(0, 100) || ''}`,
            icon: data.sender_avatar || '/favicon.ico',
            badge: '/favicon.ico',
            tag: `mention-${data.message_id}`,
            data: {
                url: `/?channel=${data.channel_id}`,
                notificationId: data.notification_id,
            },
            requireInteraction: false,
            actions: [
                {
                    action: 'view',
                    title: 'View Message',
                },
                {
                    action: 'dismiss',
                    title: 'Dismiss',
                },
            ],
        };

        event.waitUntil(self.registration.showNotification(title, options));
    } catch (err) {
        console.error('[SW] Error processing push event:', err);
    }
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    if (event.action === 'dismiss') return;

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (clientList) {
                // If a window is already open, focus it and navigate
                for (const client of clientList) {
                    if ('focus' in client) {
                        client.focus();
                        client.navigate(url);
                        return;
                    }
                }
                // Otherwise open a new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            }),
    );
});

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});
