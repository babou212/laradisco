<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next';

type DmGroup = {
    id: number;
    name: string;
    other_user?: {
        id: number;
        username: string;
        avatar_path: string | null;
    };
    last_message: {
        content: string;
        created_at: string;
        user_id: number;
    } | null;
    last_message_at: string | null;
};

type Props = {
    dmGroups: DmGroup[];
    selectedDmGroupId?: number;
};

defineProps<Props>();
const emit = defineEmits<{
    selectDm: [dmGroupId: number];
    switchToChannels: [];
}>();

const selectDm = (dmGroupId: number) => {
    emit('selectDm', dmGroupId);
};

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);

    if (diffInHours < 24) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        });
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};
</script>

<template>
    <div
        class="flex h-full w-60 flex-col border-r border-sidebar-border bg-sidebar"
    >
        <!-- Header -->
        <div
            class="flex items-center justify-between border-b border-sidebar-border px-4 py-3"
        >
            <h2 class="text-sm font-semibold text-sidebar-foreground">
                Direct Messages
            </h2>
        </div>

        <!-- DM List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Back Button -->
            <div class="border-b border-sidebar-border px-2 py-2">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded px-2 py-2 text-sm text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                    @click="$emit('switchToChannels')"
                >
                    <ArrowLeft :size="18" />
                    Back
                </button>
            </div>

            <div class="p-2">
                <div
                    v-if="dmGroups.length === 0"
                    class="px-2 py-4 text-center text-sm text-sidebar-foreground/50"
                >
                    No direct messages yet
                </div>

                <button
                    v-for="dm in dmGroups"
                    :key="dm.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded px-2 py-2 text-left transition-colors"
                    :class="
                        selectedDmGroupId === dm.id
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                            : 'text-sidebar-foreground hover:bg-sidebar-accent/50'
                    "
                    @click="selectDm(dm.id)"
                >
                    <!-- Avatar -->
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                    >
                        {{ dm.other_user?.username?.[0]?.toUpperCase() || '?' }}
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="truncate text-sm font-medium">{{
                                dm.name
                            }}</span>
                            <span
                                v-if="dm.last_message_at"
                                class="shrink-0 text-xs text-sidebar-foreground/50"
                            >
                                {{ formatTime(dm.last_message_at) }}
                            </span>
                        </div>
                        <p
                            v-if="dm.last_message"
                            class="truncate text-xs text-sidebar-foreground/70"
                        >
                            {{ dm.last_message.content }}
                        </p>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
