<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { usePresenceStore } from '@/stores/presence';
import type { UserStatusType } from '@/types';
import UserProfilePanel from './UserProfilePanel.vue';

const presenceStore = usePresenceStore();
const page = usePage();

onMounted(() => {
    presenceStore.connect();
});

const selectedUser = ref<any>(null);
const showUserProfile = ref(false);

const currentUserId = computed(() => page.props.auth?.user?.id);

const usersByStatus = computed(() => {
    const online = presenceStore.onlineUsers.filter(
        (u) => u.status === 'online',
    );
    const idle = presenceStore.onlineUsers.filter((u) => u.status === 'idle');
    const dnd = presenceStore.onlineUsers.filter((u) => u.status === 'dnd');
    const offline = presenceStore.onlineUsers.filter(
        (u) => u.status === 'offline',
    );

    return { online, idle, dnd, offline };
});

const getStatusColor = (status?: UserStatusType) => {
    switch (status) {
        case 'online':
            return 'bg-green-500';
        case 'idle':
            return 'bg-orange-500';
        case 'dnd':
            return 'bg-red-500';
        case 'offline':
        default:
            return 'bg-gray-400';
    }
};

const openUserProfile = (user: any) => {
    selectedUser.value = user;
    showUserProfile.value = true;
};

const closeUserProfile = () => {
    showUserProfile.value = false;
    selectedUser.value = null;
};

const startDm = (userId: number) => {
    closeUserProfile();

    const getCsrfToken = (): string => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    fetch('/direct-message/start', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ user_id: userId }),
    })
        .then((res) => res.json())
        .then((data) => {
            router.visit(`/direct-message/${data.dm_group_id}`);
        });
};
</script>

<template>
    <div class="flex h-full w-60 flex-col border-l border-border bg-sidebar">
        <!-- Header -->
        <div
            class="flex h-12 items-center border-b border-sidebar-border px-4 font-semibold shadow-sm"
        >
            Members
        </div>

        <!-- Scrollable Members List -->
        <div class="flex-1 overflow-y-auto px-2 py-4">
            <!-- Online Users -->
            <div v-if="usersByStatus.online.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold tracking-wide text-sidebar-foreground/70 uppercase"
                >
                    Online — {{ usersByStatus.online.length }}
                </h3>
                <div class="pt-0.5">
                    <button
                        v-for="user in usersByStatus.online"
                        :key="user.id"
                        type="button"
                        class="group flex w-full cursor-pointer items-center gap-x-2 rounded px-2 py-1 text-left transition-colors hover:bg-sidebar-accent"
                        @click="openUserProfile(user)"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-sidebar"
                                :class="getStatusColor(user.status)"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="truncate text-sm font-medium text-sidebar-foreground"
                            >
                                {{ user.display_name }}
                            </div>
                            <div
                                v-if="user.custom_status"
                                class="truncate text-xs text-sidebar-foreground/60"
                            >
                                {{ user.custom_status }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Idle Users -->
            <div v-if="usersByStatus.idle.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold tracking-wide text-sidebar-foreground/70 uppercase"
                >
                    Idle — {{ usersByStatus.idle.length }}
                </h3>
                <div class="space-y-0.5">
                    <button
                        v-for="user in usersByStatus.idle"
                        :key="user.id"
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2 rounded px-2 py-1.5 transition-colors hover:bg-sidebar-accent"
                        @click="openUserProfile(user)"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-sidebar"
                                :class="getStatusColor(user.status)"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="truncate text-sm font-medium text-sidebar-foreground"
                            >
                                {{ user.display_name }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- DND Users -->
            <div v-if="usersByStatus.dnd.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold tracking-wide text-sidebar-foreground/70 uppercase"
                >
                    Do Not Disturb — {{ usersByStatus.dnd.length }}
                </h3>
                <div class="space-y-0.5">
                    <button
                        v-for="user in usersByStatus.dnd"
                        :key="user.id"
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2 rounded px-2 py-1.5 transition-colors hover:bg-sidebar-accent"
                        @click="openUserProfile(user)"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-sidebar"
                                :class="getStatusColor(user.status)"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="truncate text-sm font-medium text-sidebar-foreground"
                            >
                                {{ user.display_name }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Offline Users -->
            <div v-if="usersByStatus.offline.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold tracking-wide text-sidebar-foreground/70 uppercase"
                >
                    Offline — {{ usersByStatus.offline.length }}
                </h3>
                <div class="space-y-0.5">
                    <button
                        v-for="user in usersByStatus.offline"
                        :key="user.id"
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2 rounded px-2 py-1.5 opacity-60 transition-colors hover:bg-sidebar-accent hover:opacity-100"
                        @click="openUserProfile(user)"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-sidebar"
                                :class="getStatusColor(user.status)"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="truncate text-sm font-medium text-sidebar-foreground"
                            >
                                {{ user.display_name }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- User Profile Panel -->
        <UserProfilePanel
            v-if="selectedUser && showUserProfile"
            :user="selectedUser"
            :show="showUserProfile"
            :is-current-user="selectedUser.id === currentUserId"
            @close="closeUserProfile"
            @send-message="startDm"
        />
    </div>
</template>
