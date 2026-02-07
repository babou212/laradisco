<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DirectMessagesSidebar from '@/components/chat/DirectMessagesSidebar.vue';
import MessagesPanel from '@/components/chat/MessagesPanel.vue';

type Props = {
    dmGroups: Array<{
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
    }>;
    currentDmGroup?: {
        id: number;
        name: string;
        other_user?: {
            id: number;
            username: string;
            avatar_path: string | null;
        };
        messages?: Array<any>;
    };
};

const props = defineProps<Props>();

const selectedDmGroupId = ref(props.currentDmGroup?.id);

const selectedDmGroup = computed(() => {
    return props.currentDmGroup;
});

const handleSelectDm = (dmGroupId: number) => {
    selectedDmGroupId.value = dmGroupId;
    router.visit(`/direct-message/${dmGroupId}`);
};

const switchToChannels = () => {
    router.visit('/');
};
</script>

<template>
    <Head title="Direct Messages" />

    <div class="flex h-screen overflow-hidden bg-background">
        <!-- Left Sidebar: DMs List -->
        <DirectMessagesSidebar
            :dm-groups="dmGroups"
            :selected-dm-group-id="selectedDmGroupId"
            @select-dm="handleSelectDm"
            @switch-to-channels="switchToChannels"
        />

        <!-- Middle: Messages Panel -->
        <MessagesPanel
            v-if="selectedDmGroup"
            :channel="selectedDmGroup"
            :channel-id="selectedDmGroupId"
            :is-dm="true"
        />

        <!-- Empty state when no conversation selected -->
        <div
            v-else
            class="flex flex-1 items-center justify-center bg-background"
        >
            <div class="text-center">
                <p class="text-muted-foreground">No direct message selected</p>
            </div>
        </div>
    </div>
</template>
