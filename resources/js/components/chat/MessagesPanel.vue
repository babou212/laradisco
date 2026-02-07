<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Hash } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import echo from '@/lib/echo';
import Message, { type MessageData, type MessageReaction } from './Message.vue';
import MessageInput from './MessageInput.vue';
import TypingIndicator from './TypingIndicator.vue';

type Props = {
    channel?: {
        id: number;
        name: string;
        topic: string | null;
        messages?: MessageData[];
    };
    channelId?: number;
};

const props = defineProps<Props>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const messagesContainer = ref<HTMLElement>();

// Local copy of messages to avoid prop mutation
const messages = ref<MessageData[]>([]);

// Edit state
const editingMessageId = ref<number | null>(null);
const editContent = ref('');

// Emoji picker state
const emojiPickerMessageId = ref<number | null>(null);

// Typing indicator state
const typingUsers = ref<Map<number, { username: string; timeout: ReturnType<typeof setTimeout> }>>(new Map());
let typingDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const handleClickOutside = (e: MouseEvent) => {
    if (emojiPickerMessageId.value !== null) {
        const target = e.target as HTMLElement;
        const emojiPicker = target.closest('.emoji-picker-container');
        const reactionButton = target.closest('[data-reaction-button]');
        
        if (!emojiPicker && !reactionButton) {
            emojiPickerMessageId.value = null;
        }
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

// Listen for new messages on the current channel
let currentChannelListener: string | null = null;

const joinChannel = (channelId: number) => {
    leaveChannel();
    currentChannelListener = `channel.${channelId}`;
    echo.join(currentChannelListener)
        .listen('MessageSent', (data: { message: MessageData }) => {
            messages.value.push(data.message);
            scrollToBottom();
        })
        .listen('MessageEdited', (data: { message: MessageData }) => {
            const idx = messages.value.findIndex(m => m.id === data.message.id);
            if (idx !== -1) {
                messages.value[idx].content = data.message.content;
                messages.value[idx].is_edited = true;
                messages.value[idx].edited_at = data.message.edited_at;
            }
        })
        .listen('MessageDeleted', (data: { message_id: number }) => {
            const idx = messages.value.findIndex(m => m.id === data.message_id);
            if (idx !== -1) {
                messages.value.splice(idx, 1);
            }
        })
        .listen('ReactionToggled', (data: { reaction: MessageReaction; added: boolean }) => {
            const msg = messages.value.find(m => m.id === data.reaction.message_id);
            if (msg) {
                if (data.added) {
                    msg.reactions.push(data.reaction);
                } else {
                    const idx = msg.reactions.findIndex(
                        r => r.user_id === data.reaction.user_id && r.emoji === data.reaction.emoji,
                    );
                    if (idx !== -1) {
                        msg.reactions.splice(idx, 1);
                    }
                }
            }
        })
        .listen('UserTyping', (data: { user_id: number; username: string; is_typing: boolean }) => {
            if (data.user_id === currentUser.value.id) return;

            if (data.is_typing) {
                const existing = typingUsers.value.get(data.user_id);
                if (existing) clearTimeout(existing.timeout);

                const timeout = setTimeout(() => {
                    typingUsers.value.delete(data.user_id);
                }, 4000);

                typingUsers.value.set(data.user_id, { username: data.username, timeout });
            } else {
                const existing = typingUsers.value.get(data.user_id);
                if (existing) clearTimeout(existing.timeout);
                typingUsers.value.delete(data.user_id);
            }
        });
};

const leaveChannel = () => {
    if (currentChannelListener) {
        echo.leave(currentChannelListener);
        currentChannelListener = null;
    }
    typingUsers.value.clear();
};

watch(() => props.channelId, (newId) => {
    if (newId) {
        joinChannel(newId);
        scrollToBottom();
    }
}, { immediate: true });

watch(() => props.channel?.messages, (newMessages) => {
    if (newMessages) {
        messages.value = [...newMessages];
    }
}, { immediate: true, deep: true });

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    leaveChannel();
    document.removeEventListener('click', handleClickOutside);
});

// Message actions
const sendMessage = (content: string) => {
    if (!props.channelId) return;

    router.post(
        `/channels/${props.channelId}/messages`,
        { content },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                // Fetch updated channel messages
                fetch(`/channels/${props.channelId}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                })
                    .then(res => res.json())
                    .then((data: { channel: any; messages: MessageData[] }) => {
                        messages.value = data.messages;
                        scrollToBottom();
                    });
            },
        },
    );
};

const startEdit = (message: MessageData) => {
    editingMessageId.value = message.id;
    editContent.value = message.content;
};

const cancelEdit = () => {
    editingMessageId.value = null;
    editContent.value = '';
};

const saveEdit = (message: MessageData) => {
    if (!editContent.value.trim() || !props.channelId) return;

    fetch(`/channels/${props.channelId}/messages/${message.id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ content: editContent.value }),
    }).then(res => {
        if (res.ok) {
            message.content = editContent.value;
            message.is_edited = true;
            message.edited_at = new Date().toISOString();
            cancelEdit();
        }
    });
};

const deleteMessage = (message: MessageData) => {
    if (!props.channelId) return;

    fetch(`/channels/${props.channelId}/messages/${message.id}`, {
        method: 'DELETE',
        headers: {
            'X-XSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
    }).then(res => {
        if (res.ok) {
            const idx = messages.value.findIndex(m => m.id === message.id);
            if (idx !== -1) {
                messages.value.splice(idx, 1);
            }
        }
    });
};

const toggleReaction = (message: MessageData, emoji: string) => {
    if (!props.channelId) return;
    emojiPickerMessageId.value = null;

    fetch(`/channels/${props.channelId}/messages/${message.id}/reactions`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ emoji }),
    }).then(res => res.json()).then((data: { added: boolean }) => {
        if (data.added) {
            message.reactions.push({
                id: 0,
                message_id: message.id,
                user_id: currentUser.value.id,
                emoji,
            });
        } else {
            const idx = message.reactions.findIndex(
                r => r.user_id === currentUser.value.id && r.emoji === emoji,
            );
            if (idx !== -1) {
                message.reactions.splice(idx, 1);
            }
        }
    });
};

const emitTyping = () => {
    if (!props.channelId) return;

    if (typingDebounceTimer) clearTimeout(typingDebounceTimer);

    fetch(`/channels/${props.channelId}/typing`, {
        method: 'POST',
        headers: {
            'X-XSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
    });

    typingDebounceTimer = setTimeout(() => {
        typingDebounceTimer = null;
    }, 2000);
};
</script>

<template>
    <div class="flex flex-1 flex-col bg-background">
        <!-- Channel Header -->
        <div
            class="flex h-12 items-center border-b border-border px-4 shadow-sm"
        >
            <Hash :size="20" class="mr-2 text-muted-foreground" />
            <div class="flex-1">
                <h2 class="font-semibold">
                    {{ channel?.name || 'Select a channel' }}
                </h2>
                <p v-if="channel?.topic" class="text-xs text-muted-foreground">
                    {{ channel.topic }}
                </p>
            </div>
        </div>

        <!-- Messages Area -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-4"
        >
            <!-- Empty state -->
            <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
                <div class="text-center text-muted-foreground">
                    <Hash :size="48" class="mx-auto mb-2 opacity-50" />
                    <p class="text-lg font-semibold">Welcome to #{{ channel?.name }}</p>
                    <p class="text-sm">This is the start of your conversation.</p>
                </div>
            </div>

            <!-- Messages list -->
            <div v-else class="space-y-1">
                <Message
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                    :is-editing="editingMessageId === message.id"
                    :edit-content="editContent"
                    :show-emoji-picker="emojiPickerMessageId === message.id"
                    @start-edit="startEdit(message)"
                    @cancel-edit="cancelEdit"
                    @save-edit="saveEdit(message)"
                    @delete="deleteMessage(message)"
                    @toggle-reaction="emoji => toggleReaction(message, emoji)"
                    @toggle-emoji-picker="emojiPickerMessageId = emojiPickerMessageId === message.id ? null : message.id"
                    @update-edit-content="editContent = $event"
                />
            </div>
        </div>

        <TypingIndicator :typing-users="typingUsers" />

        <MessageInput
            :channel-name="channel?.name"
            @send="sendMessage"
            @typing="emitTyping"
        />
    </div>
</template>