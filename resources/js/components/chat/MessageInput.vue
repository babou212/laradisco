<script setup lang="ts">
import { Image, Send, Smile } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import EmojiPicker from './EmojiPicker.vue';
import GifPicker from './GifPicker.vue';

interface Props {
    channelName?: string;
}

interface Emits {
    (e: 'send', content: string): void;
    (e: 'typing'): void;
}

defineProps<Props>();
const emit = defineEmits<Emits>();

const messageInput = ref('');
const showEmojiPicker = ref(false);
const showGifPicker = ref(false);
const textareaRef = ref<HTMLTextAreaElement>();
const emojiPickerRef = ref<HTMLElement>();
const gifPickerRef = ref<HTMLElement>();

const sendMessage = () => {
    if (!messageInput.value.trim()) return;
    emit('send', messageInput.value);
    messageInput.value = '';
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

const handleInput = () => {
    emit('typing');
};

const onSelectEmoji = (emoji: string) => {
    const cursorPos = textareaRef.value?.selectionStart || messageInput.value.length;
    messageInput.value =
        messageInput.value.slice(0, cursorPos) +
        emoji +
        messageInput.value.slice(cursorPos);
    showEmojiPicker.value = false;
    
    // Focus back on textarea and move cursor after emoji
    setTimeout(() => {
        textareaRef.value?.focus();
        const newPos = cursorPos + emoji.length;
        textareaRef.value?.setSelectionRange(newPos, newPos);
    }, 0);
};

const onSelectGif = (gifUrl: string) => {
    messageInput.value = gifUrl;
    showGifPicker.value = false;
    sendMessage();
};

const handleClickOutside = (e: MouseEvent) => {
    if (showEmojiPicker.value && emojiPickerRef.value && !emojiPickerRef.value.contains(e.target as Node)) {
        const emojiButton = (e.target as HTMLElement).closest('[data-emoji-button]');
        if (!emojiButton) {
            showEmojiPicker.value = false;
        }
    }
    if (showGifPicker.value && gifPickerRef.value && !gifPickerRef.value.contains(e.target as Node)) {
        const gifButton = (e.target as HTMLElement).closest('[data-gif-button]');
        if (!gifButton) {
            showGifPicker.value = false;
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative border-t border-border p-4">
        <!-- Emoji Picker Popup -->
        <div
            v-if="showEmojiPicker"
            ref="emojiPickerRef"
            class="absolute bottom-full left-4 mb-2 z-10"
        >
            <EmojiPicker @select="onSelectEmoji" />
        </div>

        <!-- GIF Picker Popup -->
        <div
            v-if="showGifPicker"
            ref="gifPickerRef"
            class="absolute bottom-full left-4 mb-2 z-10"
        >
            <GifPicker @select="onSelectGif" />
        </div>

        <div
            class="flex items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 focus-within:ring-2 focus-within:ring-ring"
        >
            <button
                type="button"
                data-emoji-button
                class="shrink-0 rounded p-1.5 transition-colors"
                :class="showEmojiPicker ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                @click="showEmojiPicker = !showEmojiPicker; showGifPicker = false"
            >
                <Smile :size="18" />
            </button>
            <button
                type="button"
                data-gif-button
                class="shrink-0 rounded p-1.5 transition-colors"
                :class="showGifPicker ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                @click="showGifPicker = !showGifPicker; showEmojiPicker = false"
            >
                <Image :size="18" />
            </button>
            <textarea
                ref="textareaRef"
                v-model="messageInput"
                rows="1"
                class="flex-1 resize-none bg-transparent text-sm outline-none placeholder:text-muted-foreground py-1.5"
                :placeholder="`Message #${channelName || 'channel'}`"
                @keydown="handleKeydown"
                @input="handleInput"
            />
            <button
                type="button"
                class="shrink-0 rounded p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
                :disabled="!messageInput.trim()"
                @click="sendMessage"
            >
                <Send :size="18" />
            </button>
        </div>
    </div>
</template>
