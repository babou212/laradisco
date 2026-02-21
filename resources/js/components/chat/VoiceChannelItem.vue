<script setup lang="ts">
import { Volume2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useVoiceStore, type VoiceParticipant } from '@/stores/voice';

type Props = {
    channel: {
        id: number;
        name: string;
        topic: string | null;
        type: string;
    };
};

const props = defineProps<Props>();

const voiceStore = useVoiceStore();

const isCurrentChannel = computed(() => {
    return voiceStore.currentChannel?.id === props.channel.id;
});

const channelParticipants = computed<VoiceParticipant[]>(() => {
    // If this is the channel we're connected to, show LiveKit participants
    if (isCurrentChannel.value) {
        return voiceStore.currentParticipants;
    }

    // Otherwise show presence-based participants
    return voiceStore.getChannelParticipants(props.channel.id);
});

const handleClick = () => {
    if (isCurrentChannel.value) {
        return; // Already connected to this channel
    }

    voiceStore.joinChannel(props.channel.id, props.channel.name);
};
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm transition-colors"
            :class="
                isCurrentChannel
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'
            "
            @click="handleClick"
        >
            <Volume2 :size="16" class="shrink-0" />
            <span class="truncate">{{ channel.name }}</span>
        </button>

        <div
            v-if="channelParticipants.length > 0"
            class="ml-6 space-y-0.5 py-0.5"
        >
            <div
                v-for="participant in channelParticipants"
                :key="participant.id"
                class="flex items-center gap-2 rounded px-2 py-1"
            >
                <div
                    class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200"
                    :class="
                        participant.isSpeaking
                            ? 'ring-2 ring-green-500 bg-green-600 text-white'
                            : 'bg-primary text-primary-foreground'
                    "
                >
                    {{ participant.displayName?.[0]?.toUpperCase() || 'U' }}
                </div>

                <span
                    class="truncate text-xs text-sidebar-foreground/70"
                    :class="{ 'text-sidebar-foreground': participant.isSpeaking }"
                >
                    {{ participant.displayName || participant.username }}
                </span>

                <span v-if="participant.isMuted" class="ml-auto shrink-0">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-3 text-red-400"
                    >
                        <line x1="1" y1="1" x2="23" y2="23" />
                        <path
                            d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"
                        />
                        <path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2c0 .7-.1 1.37-.29 2" />
                        <line x1="12" y1="19" x2="12" y2="23" />
                        <line x1="8" y1="23" x2="16" y2="23" />
                    </svg>
                </span>
            </div>
        </div>
    </div>
</template>
