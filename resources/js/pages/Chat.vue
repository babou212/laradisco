<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ChannelSidebar from '@/components/chat/ChannelSidebar.vue';
import MessagesPanel from '@/components/chat/MessagesPanel.vue';
import OnlineUsersSidebar from '@/components/chat/OnlineUsersSidebar.vue';

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
    currentChannel?: {
        id: number;
        name: string;
        topic: string | null;
        messages?: Array<any>;
    };
};

const props = defineProps<Props>();

const selectedChannelId = ref(
    props.currentChannel?.id || props.categories[0]?.channels[0]?.id,
);

const selectedChannel = computed(() => {
    // If we have currentChannel with the matching ID, use it (it has messages)
    if (props.currentChannel?.id === selectedChannelId.value) {
        return props.currentChannel;
    }

    // Otherwise look in categories (won't have messages)
    for (const category of props.categories) {
        const channel = category.channels.find(
            (c) => c.id === selectedChannelId.value,
        );
        if (channel) return channel;
    }

    return props.currentChannel;
});

const handleSelectChannel = (channelId: number) => {
    selectedChannelId.value = channelId;
    // Fetch the channel messages
    router.get(
        `/?channel=${channelId}`,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const switchToDms = () => {
    router.visit('/direct-message');
};
</script>

<template>
    <Head title="Chat" />

    <div class="flex h-screen overflow-hidden bg-background">
        <!-- Left Sidebar: Channels -->
        <ChannelSidebar
            :categories="categories"
            :direct-messages="[]"
            :selected-channel-id="selectedChannelId"
            @select-channel="handleSelectChannel"
            @switch-to-dms="switchToDms"
        />

        <!-- Middle: Messages Panel -->
        <MessagesPanel
            v-if="selectedChannel"
            :channel="selectedChannel"
            :channel-id="selectedChannelId"
        />

        <!-- Right Sidebar: Online Users -->
        <OnlineUsersSidebar />
    </div>
</template>
