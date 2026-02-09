<script setup lang="ts">
import { router, usePage, InfiniteScroll } from '@inertiajs/vue3';
import { Hash, MessageSquare } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import SearchInput from '@/components/SearchInput.vue';
import echo from '@/lib/echo';
import type { User } from '@/types/auth';
import Message, { type MessageData, type MessageReaction } from './Message.vue';
import MessageInput from './MessageInput.vue';
import TypingIndicator from './TypingIndicator.vue';

type ChannelData = {
    id: number;
    name: string;
    topic?: string | null;
    other_user?: {
        id: number;
        username: string;
        avatar_path: string | null;
    };
};

type MessagesData = {
    data: MessageData[];
    next_cursor?: string | null;
    prev_cursor?: string | null;
    next_page_url?: string | null;
    prev_page_url?: string | null;
};

type Props = {
    channel?: ChannelData;
    messages?: MessagesData;
    channelId?: number;
    isDm?: boolean;
};

type PageProps = {
    name: string;
    auth: {
        user: User;
    };
    sidebarOpen: boolean;
    currentChannel?: ChannelData;
    messages?: MessagesData;
};

const props = withDefaults(defineProps<Props>(), {
    isDm: false,
});

const page = usePage<PageProps>();
const currentUser = computed((): User => page.props.auth.user);

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const messagesContainer = ref<HTMLElement>();

const editingMessageId = ref<number | null>(null);
const editContent = ref('');

const emojiPickerMessageId = ref<number | null>(null);

const replyingToMessage = ref<MessageData | null>(null);

const typingUsers = ref<
    Map<number, { username: string; timeout: ReturnType<typeof setTimeout> }>
>(new Map());
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
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    });
};

let currentChannelListener: string | null = null;

const joinChannel = (channelId: number, isDm: boolean = false) => {
    leaveChannel();
    currentChannelListener = isDm
        ? `direct-message.${channelId}`
        : `channel.${channelId}`;
    console.log('[MessagesPanel] Joining channel:', currentChannelListener);

    echo.join(currentChannelListener)
        .listen('MessageSent', (data: { message: MessageData }) => {
            console.log('[MessagesPanel] MessageSent event received:', data);
            if (data.message.user.id === currentUser.value.id) {
                return;
            }

            // Add new message to the messages array in page props
            if (page.props.messages?.data) {
                page.props.messages.data.push(data.message);
            }
            scrollToBottom();
        })
        .listen('MessageEdited', (data: { message: MessageData }) => {
            if (!page.props.messages?.data) return;
            const idx = page.props.messages.data.findIndex(
                (m) => m.id === data.message.id,
            );
            if (idx !== -1) {
                page.props.messages.data[idx].content = data.message.content;
                page.props.messages.data[idx].is_edited = true;
                page.props.messages.data[idx].edited_at = data.message.edited_at;
            }
        })
        .listen('MessageDeleted', (data: { message_id: number }) => {
            if (!page.props.messages?.data) return;
            const idx = page.props.messages.data.findIndex(
                (m) => m.id === data.message_id,
            );
            if (idx !== -1) {
                page.props.messages.data.splice(idx, 1);
            }
        })
        .listen(
            'ReactionToggled',
            (data: { reaction: MessageReaction; added: boolean }) => {
                if (!page.props.messages?.data) return;
                const msg = page.props.messages.data.find(
                    (m) => m.id === data.reaction.message_id,
                );
                if (msg) {
                    if (data.added) {
                        const exists = msg.reactions.some(
                            (r) =>
                                r.user_id === data.reaction.user_id &&
                                r.emoji === data.reaction.emoji,
                        );
                        if (!exists) {
                            msg.reactions.push(data.reaction);
                        }
                    } else {
                        const idx = msg.reactions.findIndex(
                            (r) =>
                                r.user_id === data.reaction.user_id &&
                                r.emoji === data.reaction.emoji,
                        );
                        if (idx !== -1) {
                            msg.reactions.splice(idx, 1);
                        }
                    }
                }
            },
        )
        .listen(
            'UserTyping',
            (data: {
                user_id: number;
                username: string;
                is_typing: boolean;
            }) => {
                if (data.user_id === currentUser.value.id) return;

                if (data.is_typing) {
                    const existing = typingUsers.value.get(data.user_id);
                    if (existing) clearTimeout(existing.timeout);

                    const timeout = setTimeout(() => {
                        typingUsers.value.delete(data.user_id);
                    }, 4000);

                    typingUsers.value.set(data.user_id, {
                        username: data.username,
                        timeout,
                    });
                } else {
                    const existing = typingUsers.value.get(data.user_id);
                    if (existing) clearTimeout(existing.timeout);
                    typingUsers.value.delete(data.user_id);
                }
            },
        );
};

const leaveChannel = () => {
    if (currentChannelListener) {
        echo.leave(currentChannelListener);
        currentChannelListener = null;
    }
    typingUsers.value.clear();
};

watch(
    () => props.channelId,
    (newId) => {
        if (newId) {
            joinChannel(newId, props.isDm);
            scrollToBottom();
        }
    },
    { immediate: true },
);

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    leaveChannel();
    document.removeEventListener('click', handleClickOutside);
});

const sendMessage = (content: string) => {
    if (!props.channelId) return;
    
    if (!page.props.messages?.data) return;

    const data: { content: string; reply_to_id?: number } = { content };
    if (replyingToMessage.value) {
        data.reply_to_id = replyingToMessage.value.id;
    }

    const endpoint = props.isDm
        ? `/direct-message/${props.channelId}/messages`
        : `/channels/${props.channelId}/messages`;

    const optimisticMessage: MessageData = {
        id: Date.now(),
        content,
        is_edited: false,
        edited_at: null,
        deleted_at: null,
        reply_to_id: replyingToMessage.value?.id || null,
        reply_to: replyingToMessage.value || null,
        user: {
            id: currentUser.value.id,
            username: currentUser.value.username as string,
            avatar_path: currentUser.value.avatar_path as string | null,
        },
        reactions: [],
        created_at: new Date().toISOString(),
    };

    page.props.messages.data.push(optimisticMessage);
    scrollToBottom();

    router.post(endpoint, data, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            replyingToMessage.value = null;
            if (!page.props.messages?.data) return;
            const idx = page.props.messages.data.findIndex(
                (m) => m.id === optimisticMessage.id,
            );
            if (idx !== -1 && page.props.messages?.data) {
                const realMessage =
                    page.props.messages.data[
                        page.props.messages.data.length - 1
                    ];
                if (realMessage) {
                    page.props.messages.data[idx] = realMessage;
                }
            }
        },
        onError: () => {
            if (!page.props.messages?.data) return;
            const idx = page.props.messages.data.findIndex(
                (m) => m.id === optimisticMessage.id,
            );
            if (idx !== -1) {
                page.props.messages.data.splice(idx, 1);
            }
        },
    });
};

const startReply = (message: MessageData) => {
    replyingToMessage.value = message;
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

    const endpoint = props.isDm
        ? `/direct-message/${props.channelId}/messages/${message.id}`
        : `/channels/${props.channelId}/messages/${message.id}`;

    fetch(endpoint, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ content: editContent.value }),
    }).then((res) => {
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
    
    if (!page.props.messages?.data) return;

    const endpoint = props.isDm
        ? `/direct-message/${props.channelId}/messages/${message.id}`
        : `/channels/${props.channelId}/messages/${message.id}`;

    fetch(endpoint, {
        method: 'DELETE',
        headers: {
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
    }).then((res) => {
        if (res.ok) {
            if (!page.props.messages?.data) return;
            const idx = page.props.messages.data.findIndex((m) => m.id === message.id);
            if (idx !== -1) {
                page.props.messages.data.splice(idx, 1);
            }
        }
    });
};

const toggleReaction = (message: MessageData, emoji: string) => {
    if (!props.channelId) return;
    emojiPickerMessageId.value = null;

    const endpoint = props.isDm
        ? `/direct-message/${props.channelId}/messages/${message.id}/reactions`
        : `/channels/${props.channelId}/messages/${message.id}/reactions`;

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ emoji }),
    })
        .then((res) => res.json())
        .then((data: { added: boolean }) => {
            if (data.added) {
                message.reactions.push({
                    id: 0,
                    message_id: message.id,
                    user_id: currentUser.value.id,
                    emoji,
                });
            } else {
                const idx = message.reactions.findIndex(
                    (r) =>
                        r.user_id === currentUser.value.id && r.emoji === emoji,
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

    const endpoint = props.isDm
        ? `/direct-message/${props.channelId}/typing`
        : `/channels/${props.channelId}/typing`;

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'X-XSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
    });

    typingDebounceTimer = setTimeout(() => {
        typingDebounceTimer = null;
    }, 2000);
};
</script>

<template>
    <div class="flex flex-1 flex-col bg-background">
        <!-- Header -->
        <div
            class="flex h-12 items-center border-b border-border px-4 shadow-sm"
        >
            <Hash v-if="!isDm" :size="20" class="mr-2 text-muted-foreground" />
            <MessageSquare
                v-else
                :size="20"
                class="mr-2 text-muted-foreground"
            />
            <div class="flex-1">
                <h2 class="font-semibold">
                    {{ channel?.name || 'Select a channel' }}
                </h2>
                <p v-if="channel?.topic" class="text-xs text-muted-foreground">
                    {{ channel.topic }}
                </p>
            </div>

            <div class="ml-4">
                <SearchInput />
            </div>
        </div>

        <!-- Messages Area -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4">
            <!-- Empty state -->
            <div
                v-if="!messages?.data || messages.data.length === 0"
                class="flex h-full items-center justify-center"
            >
                <div class="text-center text-muted-foreground">
                    <Hash :size="48" class="mx-auto mb-2 opacity-50" />
                    <p class="text-lg font-semibold">
                        Welcome to #{{ channel?.name }}
                    </p>
                    <p class="text-sm">
                        This is the start of your conversation.
                    </p>
                </div>
            </div>

            <InfiniteScroll 
                v-else
                data="messages" 
                reverse
                only-previous
                preserve-url
            >
                <template #default="{ loadingPrevious }">
                    <div class="space-y-1">
                        <div v-if="loadingPrevious" class="flex justify-center py-2">
                            <div
                                class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"
                            ></div>
                        </div>

                        <Message
                            v-for="message in messages.data"
                            :key="message.id"
                            :message="message"
                            :is-editing="editingMessageId === message.id"
                            :edit-content="editContent"
                            :show-emoji-picker="emojiPickerMessageId === message.id"
                            @start-edit="startEdit(message)"
                            @cancel-edit="cancelEdit"
                            @save-edit="saveEdit(message)"
                            @delete="deleteMessage(message)"
                            @reply="startReply(message)"
                            @toggle-reaction="(emoji) => toggleReaction(message, emoji)"
                            @toggle-emoji-picker="
                                emojiPickerMessageId =
                                    emojiPickerMessageId === message.id
                                        ? null
                                        : message.id
                            "
                            @update-edit-content="editContent = $event"
                        />
                    </div>
                </template>
            </InfiniteScroll>
        </div>

        <TypingIndicator :typing-users="typingUsers" />

        <MessageInput
            :channel-name="channel?.name"
            :replying-to="replyingToMessage"
            @send="sendMessage"
            @typing="emitTyping"
            @cancel-reply="replyingToMessage = null"
        />
    </div>
</template>
