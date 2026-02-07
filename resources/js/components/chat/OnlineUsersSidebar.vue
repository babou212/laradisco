<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { usePresenceStore } from '@/stores/presence';
import type { UserStatusType } from '@/types';

const presenceStore = usePresenceStore();

onMounted(() => {
    presenceStore.connect();
});

const usersByStatus = computed(() => {
    const online = presenceStore.onlineUsers.filter((u) => u.status === 'online');
    const idle = presenceStore.onlineUsers.filter((u) => u.status === 'idle');
    const dnd = presenceStore.onlineUsers.filter((u) => u.status === 'dnd');
    const offline = presenceStore.onlineUsers.filter((u) => u.status === 'offline');

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
                    class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/70"
                >
                    Online — {{ usersByStatus.online.length }}
                </h3>
                <div class="space-y-1">
                    <div
                        v-for="user in usersByStatus.online"
                        :key="user.id"
                        class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-sidebar-accent"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-sidebar"
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
                    </div>
                </div>
            </div>

            <!-- Idle Users -->
            <div v-if="usersByStatus.idle.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/70"
                >
                    Idle — {{ usersByStatus.idle.length }}
                </h3>
                <div class="space-y-1">
                    <div
                        v-for="user in usersByStatus.idle"
                        :key="user.id"
                        class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-sidebar-accent"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-sidebar"
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
                    </div>
                </div>
            </div>

            <!-- DND Users -->
            <div v-if="usersByStatus.dnd.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/70"
                >
                    Do Not Disturb — {{ usersByStatus.dnd.length }}
                </h3>
                <div class="space-y-1">
                    <div
                        v-for="user in usersByStatus.dnd"
                        :key="user.id"
                        class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-sidebar-accent"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-sidebar"
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
                    </div>
                </div>
            </div>

            <!-- Offline Users -->
            <div v-if="usersByStatus.offline.length > 0" class="mb-4">
                <h3
                    class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-sidebar-foreground/70"
                >
                    Offline — {{ usersByStatus.offline.length }}
                </h3>
                <div class="space-y-1">
                    <div
                        v-for="user in usersByStatus.offline"
                        :key="user.id"
                        class="flex items-center gap-2 rounded px-2 py-1.5 opacity-60 hover:bg-sidebar-accent hover:opacity-100"
                    >
                        <div class="relative">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ user.display_name[0].toUpperCase() }}
                            </div>
                            <div
                                class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-sidebar"
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
