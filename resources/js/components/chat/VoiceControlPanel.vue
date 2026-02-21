<script setup lang="ts">
import { Mic, MicOff, Volume2, VolumeOff, PhoneOff } from 'lucide-vue-next';
import { useVoiceStore } from '@/stores/voice';

const voiceStore = useVoiceStore();
</script>

<template>
    <div
        v-if="voiceStore.isConnected"
        class="border-t border-sidebar-border bg-sidebar-accent/50 px-3 py-2"
    >
        <div class="mb-2 flex items-center gap-2">
            <div class="size-2 rounded-full bg-green-500"></div>
            <span class="text-xs font-medium text-green-400">
                Voice Connected
            </span>
        </div>
        <div class="mb-2 truncate text-xs text-sidebar-foreground/60">
            {{ voiceStore.currentChannel?.name }}
        </div>

        <div class="flex items-center justify-center gap-2">
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-full transition-colors"
                :class="
                    voiceStore.isMicMuted
                        ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                        : 'bg-sidebar-accent text-sidebar-foreground/70 hover:bg-sidebar-accent/80 hover:text-sidebar-foreground'
                "
                :title="voiceStore.isMicMuted ? 'Unmute' : 'Mute'"
                @click="voiceStore.toggleMic()"
            >
                <MicOff v-if="voiceStore.isMicMuted" :size="16" />
                <Mic v-else :size="16" />
            </button>

            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-full transition-colors"
                :class="
                    voiceStore.isSoundMuted
                        ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                        : 'bg-sidebar-accent text-sidebar-foreground/70 hover:bg-sidebar-accent/80 hover:text-sidebar-foreground'
                "
                :title="voiceStore.isSoundMuted ? 'Undeafen' : 'Deafen'"
                @click="voiceStore.toggleSound()"
            >
                <VolumeOff v-if="voiceStore.isSoundMuted" :size="16" />
                <Volume2 v-else :size="16" />
            </button>

            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-full bg-red-500/20 text-red-400 transition-colors hover:bg-red-500/40"
                title="Disconnect"
                @click="voiceStore.leaveChannel()"
            >
                <PhoneOff :size="16" />
            </button>
        </div>
    </div>
</template>
