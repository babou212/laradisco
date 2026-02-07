<script setup lang="ts">
import { usePage, router, Link } from '@inertiajs/vue3';
import { Hash, ChevronDown, ChevronRight, MessageSquare, Settings, LogOut, MoreVertical } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePresenceStore } from '@/stores/presence';
import type { User, UserStatusType } from '@/types';

type Props = {
    categories: Array<{
        id: number;
        name: string;
        position: number;
        channels: Array<{
            id: number;
            name: string;
            topic: string | null;
            type: string;
        }>;
    }>;
    directMessages: Array<any>;
    selectedChannelId?: number;
};

defineProps<Props>();
const emit = defineEmits<{
    selectChannel: [channelId: number];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user as User);

const collapsedCategories = ref<Set<number>>(new Set());
const showUserPopup = ref(false);
const currentStatus = ref<UserStatusType>('online');
const currentCustomStatus = ref<string | null>((user.value?.custom_status as string) ?? null);

// Use the Pinia presence store
const presenceStore = usePresenceStore();

// Watch for changes to the current user's status from the store
watch(
    () => presenceStore.getUserStatus(user.value?.id),
    (userStatus) => {
        if (userStatus) {
            currentStatus.value = userStatus.status || 'online';
            currentCustomStatus.value = userStatus.custom_status || null;
        }
    },
    { deep: true }
);

const toggleCategory = (categoryId: number) => {
    if (collapsedCategories.value.has(categoryId)) {
        collapsedCategories.value.delete(categoryId);
    } else {
        collapsedCategories.value.add(categoryId);
    }
};

const selectChannel = (channelId: number) => {
    emit('selectChannel', channelId);
};

const setStatus = (status: UserStatusType) => {
    // Optimistically update local state
    currentStatus.value = status;
    
    // Also update the store immediately for optimistic UI
    if (user.value?.id) {
        presenceStore.updateUserStatus(user.value.id, status, currentCustomStatus.value);
    }
    
    router.post('/presence', {
        status: status,
        custom_status: currentCustomStatus.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
    showUserPopup.value = false;
};

const logout = () => {
    router.post('/logout');
};

const statusOptions = [
    { value: 'online' as UserStatusType, label: 'Online', color: 'bg-green-500' },
    { value: 'dnd' as UserStatusType, label: 'Do Not Disturb', color: 'bg-red-500' },
    { value: 'offline' as UserStatusType, label: 'Invisible', color: 'bg-gray-500' },
];
</script>

<template>
    <div class="flex h-full w-60 flex-col bg-sidebar">
        <!-- Server Name/Header -->
        <div
            class="flex h-12 items-center border-b border-sidebar-border px-4 font-semibold shadow-sm"
        >
            {{ $page.props.name || 'Laradisco' }}
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Direct Messages Link -->
            <div class="px-2 py-2">
                <Link
                    href="/dm"
                    class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                >
                    <MessageSquare :size="16" />
                    Direct Messages
                </Link>
            </div>

            <!-- Text Channels Section -->
            <div class="px-2 py-2">
                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="mb-4"
                >
                    <!-- Category Header -->
                    <button
                        type="button"
                        class="flex w-full items-center gap-1 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/70 hover:text-sidebar-foreground"
                        @click="toggleCategory(category.id)"
                    >
                        <ChevronRight
                            v-if="collapsedCategories.has(category.id)"
                            :size="12"
                            class="transition-transform"
                        />
                        <ChevronDown
                            v-else
                            :size="12"
                            class="transition-transform"
                        />
                        {{ category.name }}
                    </button>

                    <!-- Channels List -->
                    <div
                        v-if="!collapsedCategories.has(category.id)"
                        class="mt-1 space-y-0.5"
                    >
                        <button
                            v-for="channel in category.channels"
                            :key="channel.id"
                            type="button"
                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm transition-colors"
                            :class="
                                selectedChannelId === channel.id
                                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                    : 'text-sidebar-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'
                            "
                            @click="selectChannel(channel.id)"
                        >
                            <Hash :size="16" class="shrink-0" />
                            <span class="truncate">{{ channel.name }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Profile Section at Bottom -->
        <div class="relative border-t border-sidebar-border bg-sidebar-accent/30">
            <!-- Backdrop to close popup when clicking outside -->
            <div
                v-if="showUserPopup"
                class="fixed inset-0 z-10"
                @click="showUserPopup = false"
            ></div>

            <!-- User Popup Menu -->
            <div
                v-if="showUserPopup"
                class="absolute bottom-full left-0 right-0 z-20 mb-2 rounded-lg border border-sidebar-border bg-popover p-2 shadow-lg"
            >
                <!-- Status Options -->
                <div class="mb-2 space-y-1">
                    <button
                        v-for="status in statusOptions"
                        :key="status.value"
                        type="button"
                        class="flex w-full items-center gap-3 rounded px-3 py-2 text-sm text-popover-foreground transition-colors hover:bg-accent"
                        @click="setStatus(status.value)"
                    >
                        <span class="size-2.5 rounded-full" :class="status.color"></span>
                        <span>{{ status.label }}</span>
                    </button>
                </div>

                <div class="my-2 border-t border-sidebar-border"></div>

                <!-- Settings & Logout -->
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded px-3 py-2 text-sm text-popover-foreground transition-colors hover:bg-accent"
                    @click="router.get('/settings')"
                >
                    <Settings :size="16" />
                    <span>Settings</span>
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded px-3 py-2 text-sm text-destructive transition-colors hover:bg-accent"
                    @click="logout"
                >
                    <LogOut :size="16" />
                    <span>Logout</span>
                </button>
            </div>

            <!-- User Profile Button -->
            <button
                type="button"
                class="flex w-full items-center gap-3 px-3 py-3 transition-colors hover:bg-sidebar-accent/50"
                @click="showUserPopup = !showUserPopup"
            >
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                >
                    {{ user?.name?.[0]?.toUpperCase() || 'U' }}
                </div>
                <div class="min-w-0 flex-1 text-left">
                    <div class="truncate text-sm font-medium text-sidebar-foreground">
                        {{ user?.username || user?.name }}
                    </div>
                    <div class="truncate text-xs text-sidebar-foreground/60">
                        {{ currentCustomStatus || currentStatus }}
                    </div>
                </div>
                <MoreVertical :size="20" class="shrink-0 text-sidebar-foreground/60" />
            </button>
        </div>
    </div>
</template>
