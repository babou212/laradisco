<script setup lang="ts">
import EmojiPicker from 'vue3-emoji-picker';
import 'vue3-emoji-picker/css';

interface Emits {
    (e: 'select', emoji: string): void;
}

const emit = defineEmits<Emits>();

const onSelectEmoji = (emoji: { i: string; n: string[]; r: string; t: string; u: string }) => {
    emit('select', emoji.i);
};
</script>

<template>
    <div class="rounded-lg border border-border bg-background shadow-lg overflow-hidden">
        <EmojiPicker
            :native="true"
            theme="dark"
            :display-recent="true"
            @select="onSelectEmoji"
        />
    </div>
</template>
