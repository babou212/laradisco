<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AtSign,
    Bell,
    Check,
    CheckCheck,
    MessageSquare,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import { formatMessageDate } from '@/lib/utils';
import {
    useNotificationStore,
    type AppNotification,
} from '@/stores/notifications';

const notificationStore = useNotificationStore();
const showDropdown = ref(false);
const dropdownRef = ref<HTMLElement>();

const isDmNotification = (notification: AppNotification): boolean => {
    return notification.data.notification_type === 'direct_message';
};

const getMentionLabel = (notification: AppNotification): string => {
    const { data } = notification;
    if (data.mention_type === 'everyone') return '@everyone';
    if (data.mention_type === 'here') return '@here';
    return `@${data.sender_username}`;
};

const handleNotificationClick = async (notification: AppNotification) => {
    showDropdown.value = false;
    await notificationStore.markAsRead(notification.id);
    if (isDmNotification(notification)) {
        router.visit(`/direct-message/${notification.data.dm_group_id}`);
    } else {
        router.visit(`/?channel=${notification.data.channel_id}`);
    }
};

const handleClickOutside = (e: MouseEvent) => {
    if (
        showDropdown.value &&
        dropdownRef.value &&
        !dropdownRef.value.contains(e.target as Node)
    ) {
        const bellButton = (e.target as HTMLElement).closest(
            '[data-notification-bell]',
        );
        if (!bellButton) {
            showDropdown.value = false;
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative">
        <button
            data-notification-bell
            class="relative rounded p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            title="Notifications"
            @click="showDropdown = !showDropdown"
        >
            <Bell :size="20" />
            <span
                v-if="notificationStore.unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground"
            >
                {{
                    notificationStore.unreadCount > 9
                        ? '9+'
                        : notificationStore.unreadCount
                }}
            </span>
        </button>

        <div
            v-if="showDropdown"
            ref="dropdownRef"
            class="absolute top-full right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
        >
            <div
                class="flex items-center justify-between border-b border-border px-3 py-2"
            >
                <h3 class="text-sm font-semibold">Notifications</h3>
                <button
                    v-if="notificationStore.unreadCount > 0"
                    class="flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    @click="notificationStore.markAllAsRead()"
                >
                    <CheckCheck :size="14" />
                    Mark all read
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <div
                    v-if="notificationStore.notifications.length === 0"
                    class="flex flex-col items-center justify-center py-8 text-muted-foreground"
                >
                    <Bell :size="32" class="mb-2 opacity-40" />
                    <p class="text-sm">No new notifications</p>
                </div>

                <button
                    v-for="notification in notificationStore.notifications"
                    :key="notification.id"
                    class="flex w-full items-start gap-3 border-b border-border px-3 py-2.5 text-left transition-colors last:border-b-0 hover:bg-accent/50"
                    @click="handleNotificationClick(notification)"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <MessageSquare
                            v-if="isDmNotification(notification)"
                            :size="14"
                        />
                        <AtSign v-else :size="14" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1 text-xs">
                            <template v-if="isDmNotification(notification)">
                                <span class="font-semibold text-primary">
                                    {{ notification.data.sender_username }}
                                </span>
                                <span class="text-muted-foreground">
                                    sent a message
                                </span>
                            </template>
                            <template v-else>
                                <span class="font-semibold text-primary">
                                    {{ getMentionLabel(notification) }}
                                </span>
                                <span class="text-muted-foreground">
                                    in #{{ notification.data.channel_name }}
                                </span>
                            </template>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-foreground">
                            <span class="font-medium"
                                >{{ notification.data.sender_username }}:</span
                            >
                            {{ notification.data.content.substring(0, 60)
                            }}{{
                                notification.data.content.length > 60
                                    ? '...'
                                    : ''
                            }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ formatMessageDate(notification.created_at) }}
                        </p>
                    </div>
                    <div
                        role="button"
                        tabindex="0"
                        class="shrink-0 cursor-pointer rounded p-1 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-foreground"
                        title="Mark as read"
                        @click.stop="
                            notificationStore.markAsRead(notification.id)
                        "
                        @keydown.enter.stop="
                            notificationStore.markAsRead(notification.id)
                        "
                    >
                        <Check :size="14" />
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
