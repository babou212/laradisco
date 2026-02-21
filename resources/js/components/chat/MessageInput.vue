<script setup lang="ts">
import { CornerDownRight, Image, Send, Smile, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import EmojiPicker from './EmojiPicker.vue';
import GifPicker from './GifPicker.vue';
import type { MessageData } from './Message.vue';
import MentionDropdown from './MentionDropdown.vue';

interface Props {
    channelName?: string;
    replyingTo?: MessageData | null;
}

interface Emits {
    (e: 'send', content: string): void;
    (e: 'typing'): void;
    (e: 'cancelReply'): void;
}

defineProps<Props>();
const emit = defineEmits<Emits>();

const messageInput = ref('');
const showEmojiPicker = ref(false);
const showGifPicker = ref(false);
const showMentionDropdown = ref(false);
const mentionQuery = ref('');
const mentionStartIndex = ref(-1);
const textareaRef = ref<HTMLTextAreaElement>();
const emojiPickerRef = ref<HTMLElement>();
const gifPickerRef = ref<HTMLElement>();

const sendMessage = () => {
    if (!messageInput.value.trim()) return;
    emit('send', messageInput.value);
    messageInput.value = '';
    showMentionDropdown.value = false;
};

const handleKeydown = (e: KeyboardEvent) => {
    // Let MentionDropdown handle navigation keys when visible
    if (showMentionDropdown.value) {
        if (['ArrowDown', 'ArrowUp', 'Tab', 'Escape'].includes(e.key)) {
            return; // MentionDropdown's global listener handles these
        }
        if (e.key === 'Enter') {
            return; // MentionDropdown handles Enter for selection
        }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

const detectMention = () => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    const cursorPos = textarea.selectionStart;
    const textBeforeCursor = messageInput.value.slice(0, cursorPos);

    // Find the last @ symbol before cursor that's either at start or after a space
    const mentionMatch = textBeforeCursor.match(/(?:^|\s)@(\w*)$/);

    if (mentionMatch) {
        const query = mentionMatch[1];
        mentionQuery.value = query;
        mentionStartIndex.value = cursorPos - query.length - 1; // -1 for @
        showMentionDropdown.value = true;
    } else {
        showMentionDropdown.value = false;
        mentionQuery.value = '';
        mentionStartIndex.value = -1;
    }
};

const onMentionSelect = (value: string) => {
    if (mentionStartIndex.value < 0) return;

    const before = messageInput.value.slice(0, mentionStartIndex.value);
    const cursorPos =
        textareaRef.value?.selectionStart || messageInput.value.length;
    const after = messageInput.value.slice(cursorPos);

    // Handle case where @ was preceded by a space - preserve the space
    const prefix = before.length > 0 && !before.endsWith(' ') ? before : before;
    messageInput.value = `${prefix}@${value} ${after}`;
    showMentionDropdown.value = false;
    mentionQuery.value = '';
    mentionStartIndex.value = -1;

    // Move cursor after the inserted mention
    setTimeout(() => {
        const newPos = prefix.length + value.length + 2; // +2 for @ and space
        textareaRef.value?.focus();
        textareaRef.value?.setSelectionRange(newPos, newPos);
    }, 0);
};

const handleInput = () => {
    emit('typing');
    detectMention();
};

const onSelectEmoji = (emoji: string) => {
    const cursorPos =
        textareaRef.value?.selectionStart || messageInput.value.length;
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
    if (
        showEmojiPicker.value &&
        emojiPickerRef.value &&
        !emojiPickerRef.value.contains(e.target as Node)
    ) {
        const emojiButton = (e.target as HTMLElement).closest(
            '[data-emoji-button]',
        );
        if (!emojiButton) {
            showEmojiPicker.value = false;
        }
    }
    if (
        showGifPicker.value &&
        gifPickerRef.value &&
        !gifPickerRef.value.contains(e.target as Node)
    ) {
        const gifButton = (e.target as HTMLElement).closest(
            '[data-gif-button]',
        );
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
    <div class="border-t border-border">
        <!-- Reply Preview -->
        <div
            v-if="replyingTo"
            class="flex items-start gap-2 border-b border-border bg-accent/30 px-4 py-2"
        >
            <CornerDownRight
                :size="16"
                class="mt-1 shrink-0 text-muted-foreground"
            />
            <div class="min-w-0 flex-1">
                <div class="text-xs font-medium text-primary">
                    Replying to {{ replyingTo.user.username }}
                </div>
                <div class="truncate text-xs text-muted-foreground">
                    {{ replyingTo.content.substring(0, 100)
                    }}{{ replyingTo.content.length > 100 ? '...' : '' }}
                </div>
            </div>
            <button
                class="shrink-0 rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                @click="emit('cancelReply')"
            >
                <X :size="16" />
            </button>
        </div>

        <div class="relative p-4">
            <!-- Mention Dropdown -->
            <MentionDropdown
                :query="mentionQuery"
                :visible="showMentionDropdown"
                @select="onMentionSelect"
                @close="showMentionDropdown = false"
            />

            <!-- Emoji Picker Popup -->
            <div
                v-if="showEmojiPicker"
                ref="emojiPickerRef"
                class="absolute bottom-full left-4 z-10 mb-2"
            >
                <EmojiPicker @select="onSelectEmoji" />
            </div>

            <!-- GIF Picker Popup -->
            <div
                v-if="showGifPicker"
                ref="gifPickerRef"
                class="absolute bottom-full left-4 z-10 mb-2"
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
                    :class="
                        showEmojiPicker
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    @click="
                        showEmojiPicker = !showEmojiPicker;
                        showGifPicker = false;
                    "
                >
                    <Smile :size="18" />
                </button>
                <button
                    type="button"
                    data-gif-button
                    class="shrink-0 rounded p-1.5 transition-colors"
                    :class="
                        showGifPicker
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    @click="
                        showGifPicker = !showGifPicker;
                        showEmojiPicker = false;
                    "
                >
                    <Image :size="18" />
                </button>
                <textarea
                    ref="textareaRef"
                    v-model="messageInput"
                    rows="1"
                    class="flex-1 resize-none bg-transparent py-1.5 text-sm outline-none placeholder:text-muted-foreground"
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
    </div>
</template>
