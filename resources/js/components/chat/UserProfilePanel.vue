<script setup lang="ts">
import { MessageSquare } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { OnlineUser, User } from '@/types/user';

type Props = {
    user: OnlineUser | null;
    show: boolean;
    isCurrentUser?: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
    sendMessage: [userId: number];
}>();

const fullUser = ref<User | null>(null);
const loading = ref(false);

watch(
    () => props.show,
    async (show) => {
        if (show && props.user) {
            loading.value = true;
            fullUser.value = null; // Reset first
            try {
                const response = await fetch(`/api/users/${props.user.id}`);
                if (!response.ok) {
                    throw new Error(
                        `Failed to fetch user data: ${response.status}`,
                    );
                }
                const data = await response.json();
                fullUser.value = data;
            } catch (error) {
                if (import.meta.env.DEV) {
                    console.error('Failed to fetch user data:', error);
                }
                fullUser.value = null;
            } finally {
                loading.value = false;
            }
        } else {
            fullUser.value = null;
            loading.value = false;
        }
    },
    { immediate: true },
);

const statusColors = {
    online: 'bg-green-500',
    idle: 'bg-yellow-500',
    dnd: 'bg-red-500',
    offline: 'bg-gray-500',
};

const statusLabels = {
    online: 'Online',
    idle: 'Idle',
    dnd: 'Do Not Disturb',
    offline: 'Offline',
};

const userInitials = computed(() => {
    if (!props.user) return '?';
    return (
        props.user.username?.[0]?.toUpperCase() ||
        props.user.display_name?.[0]?.toUpperCase() ||
        '?'
    );
});

const displayName = computed(() => {
    if (!props.user) return 'Unknown User';
    const name = (props.user.display_name || props.user.username || '').trim();
    return name || 'Unknown User';
});

const memberSince = computed(() => {
    if (!fullUser.value?.created_at) return 'Unknown';

    const date = new Date(fullUser.value.created_at);
    if (isNaN(date.getTime())) return 'Unknown';

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
});

const handleSendMessage = () => {
    if (props.user) {
        emit('sendMessage', props.user.id);
    }
};

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show && user"
            class="fixed inset-0 z-40"
            @click="handleClose"
        ></div>
    </Transition>

    <Transition
        enter-active-class="transition-all duration-200"
        enter-from-class="opacity-0 translate-x-2"
        enter-to-class="opacity-100 translate-x-0"
        leave-active-class="transition-all duration-150"
        leave-from-class="opacity-100 translate-x-0"
        leave-to-class="opacity-0 translate-x-2"
    >
        <div
            v-if="show && user"
            class="fixed top-20 right-64 z-50 w-80 overflow-hidden rounded-lg border border-border bg-popover shadow-2xl"
        >
            <!-- Banner -->
            <div
                class="relative h-16 bg-linear-to-br from-primary/30 via-primary/20 to-primary/10"
            ></div>

            <!-- Avatar Section -->
            <div class="relative -mt-10 px-4">
                <div class="relative inline-block">
                    <!-- Avatar -->
                    <div
                        v-if="user.avatar_path"
                        class="size-20 overflow-hidden rounded-full border-4 border-popover bg-muted"
                    >
                        <img
                            :src="user.avatar_path"
                            :alt="user.username"
                            class="size-full object-cover"
                        />
                    </div>
                    <div
                        v-else
                        class="flex size-20 items-center justify-center rounded-full border-4 border-popover bg-primary text-2xl font-bold text-primary-foreground"
                    >
                        {{ userInitials }}
                    </div>

                    <!-- Status Indicator -->
                    <div
                        class="absolute right-0 bottom-0 size-5 rounded-full border-4 border-popover"
                        :class="statusColors[user.status || 'offline']"
                    ></div>
                </div>
            </div>

            <!-- User Info -->
            <div class="space-y-3 p-4 pt-2">
                <!-- Username & Status -->
                <div class="rounded-lg bg-background/50 p-3">
                    <h2 class="text-lg font-bold text-popover-foreground">
                        {{ displayName }}
                    </h2>

                    <!-- Custom Status -->
                    <p
                        v-if="user.custom_status"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ user.custom_status }}
                    </p>

                    <!-- Status Badge -->
                    <div class="mt-2 flex items-center gap-1.5">
                        <div
                            class="size-2 rounded-full"
                            :class="statusColors[user.status || 'offline']"
                        ></div>
                        <span class="text-xs text-muted-foreground">{{
                            statusLabels[user.status || 'offline']
                        }}</span>
                    </div>
                </div>

                <!-- Member Since -->
                <div v-if="!loading" class="rounded-lg bg-background/50 p-3">
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Member Since
                    </h3>
                    <p class="mt-1 text-sm text-popover-foreground">
                        {{ memberSince }}
                    </p>
                </div>

                <!-- Roles -->
                <div
                    v-if="!loading && fullUser?.roles?.length"
                    class="rounded-lg bg-background/50 p-3"
                >
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Roles
                    </h3>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="role in fullUser.roles"
                            :key="role.id"
                            class="inline-flex items-center gap-1.5 rounded-full border border-border bg-background px-2.5 py-0.5 text-xs font-medium text-popover-foreground"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :style="{ backgroundColor: role.color }"
                            ></span>
                            {{ role.name }}
                        </span>
                    </div>
                </div>

                <!-- Send Message Button -->
                <button
                    v-if="!isCurrentUser"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    @click="handleSendMessage"
                >
                    <MessageSquare :size="16" />
                    Send Message
                </button>
            </div>
        </div>
    </Transition>
</template>
