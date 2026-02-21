<script setup lang="ts">
import { AtSign, Bell, MessageSquare, X } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import {
    useNotificationStore,
    type ToastNotification,
} from '@/stores/notifications';

const notificationStore = useNotificationStore();

const isDmNotification = (notification: ToastNotification): boolean => {
    return notification.data.notification_type === 'direct_message';
};

const getNotificationIcon = (notification: ToastNotification) => {
    if (isDmNotification(notification)) return MessageSquare;
    return notification.data.mention_type === 'user' ? AtSign : Bell;
};

const getMentionLabel = (notification: ToastNotification): string => {
    const { data } = notification;
    if (data.mention_type === 'everyone') return '@everyone';
    if (data.mention_type === 'here') return '@here';
    return `@${data.sender_username}`;
};

const handleClick = (notification: ToastNotification) => {
    notificationStore.markAsRead(notification.id);
    notificationStore.dismissToast(notification.id);
    if (isDmNotification(notification)) {
        router.visit(`/direct-message/${notification.data.dm_group_id}`);
    } else {
        router.visit(`/?channel=${notification.data.channel_id}`);
    }
};
</script>

<template>
    <div
        class="pointer-events-none fixed top-4 right-4 z-[100] flex flex-col gap-2"
    >
        <Transition
            v-for="toast in notificationStore.toasts"
            :key="toast.id"
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition-all duration-300 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-if="!toast.dismissing"
                class="pointer-events-auto w-80 cursor-pointer overflow-hidden rounded-lg border border-border bg-popover shadow-lg transition-colors hover:bg-accent/50"
                @click="handleClick(toast)"
            >
                <div class="flex items-start gap-3 p-3">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <component
                            :is="getNotificationIcon(toast)"
                            :size="16"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex items-center gap-1 text-sm font-semibold"
                        >
                            <template v-if="isDmNotification(toast)">
                                <span class="text-primary">
                                    {{ toast.data.sender_username }}
                                </span>
                                <span class="text-muted-foreground">
                                    sent a message
                                </span>
                            </template>
                            <template v-else>
                                <span class="text-primary">
                                    {{ getMentionLabel(toast) }}
                                </span>
                                <span class="text-muted-foreground">
                                    in #{{ toast.data.channel_name }}
                                </span>
                            </template>
                        </div>
                        <div class="mt-0.5 flex items-baseline gap-1.5 text-sm">
                            <span class="font-medium">
                                {{ toast.data.sender_username }}:
                            </span>
                            <span class="truncate text-muted-foreground">
                                {{ toast.data.content.substring(0, 80)
                                }}{{
                                    toast.data.content.length > 80 ? '...' : ''
                                }}
                            </span>
                        </div>
                    </div>

                    <button
                        class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        @click.stop="notificationStore.dismissToast(toast.id)"
                    >
                        <X :size="14" />
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
