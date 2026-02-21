<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CornerDownRight, Pencil, SmilePlus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatMessageDate } from '@/lib/utils';
import EmojiPicker from './EmojiPicker.vue';

export interface MessageReaction {
    id: number;
    message_id: number;
    user_id: number;
    emoji: string;
}

export interface MessageData {
    id: number;
    content: string;
    is_edited: boolean;
    edited_at: string | null;
    deleted_at: string | null;
    reply_to_id: number | null;
    reply_to?: {
        id: number;
        content: string;
        user: {
            id: number;
            username: string;
            avatar_path: string | null;
        };
    } | null;
    user: {
        id: number;
        username: string;
        avatar_path: string | null;
    };
    reactions: MessageReaction[];
    created_at: string;
}

interface Props {
    message: MessageData;
    isEditing: boolean;
    editContent: string;
    showEmojiPicker: boolean;
}

interface Emits {
    (e: 'startEdit'): void;
    (e: 'cancelEdit'): void;
    (e: 'saveEdit'): void;
    (e: 'delete'): void;
    (e: 'reply'): void;
    (e: 'toggleReaction', emoji: string): void;
    (e: 'toggleEmojiPicker'): void;
    (e: 'updateEditContent', content: string): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const groupedReactions = computed(() => {
    const map = new Map<
        string,
        { emoji: string; count: number; userReacted: boolean }
    >();
    for (const r of props.message.reactions) {
        const existing = map.get(r.emoji);
        if (existing) {
            existing.count++;
            if (r.user_id === currentUser.value.id) existing.userReacted = true;
        } else {
            map.set(r.emoji, {
                emoji: r.emoji,
                count: 1,
                userReacted: r.user_id === currentUser.value.id,
            });
        }
    }
    return Array.from(map.values());
});

const handleEditKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        emit('saveEdit');
    }
    if (e.key === 'Escape') {
        emit('cancelEdit');
    }
};

const extractYouTubeId = (url: string): string | null => {
    const patterns = [
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\s?]+)/,
        /youtube\.com\/watch\?.*v=([^&\s?]+)/,
    ];

    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match) return match[1];
    }
    return null;
};

const youtubeVideoId = computed(() => {
    const urlPattern =
        /(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)[^\s]+)/;
    const match = props.message.content.match(urlPattern);
    if (match) {
        return extractYouTubeId(match[1]);
    }
    return null;
});

const messageWithoutYoutubeUrl = computed(() => {
    if (!youtubeVideoId.value) return props.message.content;
    const urlPattern =
        /(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)[^\s]+)/g;
    return props.message.content.replace(urlPattern, '').trim();
});

const isGifUrl = computed(() => {
    return (
        props.message.content.match(/^https?:\/\/.*\.gif$/i) ||
        props.message.content.includes('tenor.com') ||
        props.message.content.includes('media.tenor.com')
    );
});

/**
 * Parse message content and convert @mentions to highlighted HTML spans.
 */
const parseMentions = (text: string): string => {
    // Escape HTML first
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    // Replace @everyone and @here with highlighted spans
    return escaped.replace(/@(everyone|here|\w+)/g, (match, name) => {
        const isSpecial = name === 'everyone' || name === 'here';
        const classes = isSpecial
            ? 'mention mention-special cursor-pointer rounded bg-primary/20 px-1 py-0.5 font-medium text-primary hover:bg-primary/30'
            : 'mention mention-user cursor-pointer rounded bg-primary/20 px-1 py-0.5 font-medium text-primary hover:bg-primary/30';
        return `<span class="${classes}" data-mention="${name}">${match}</span>`;
    });
};

const renderedContent = computed(() => {
    return parseMentions(props.message.content);
});

const renderedContentWithoutYoutube = computed(() => {
    if (!messageWithoutYoutubeUrl.value) return '';
    return parseMentions(messageWithoutYoutubeUrl.value);
});
</script>

<template>
    <div class="group relative -mx-2 flex gap-3 rounded p-2 hover:bg-accent/50">
        <!-- Avatar -->
        <div
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
        >
            {{ message.user.username[0].toUpperCase() }}
        </div>

        <!-- Message Content -->
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2">
                <span class="text-sm font-semibold">
                    {{ message.user.username }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ formatMessageDate(message.created_at) }}
                </span>
                <span
                    v-if="message.is_edited"
                    class="text-xs text-muted-foreground italic"
                >
                    (edited)
                </span>
            </div>

            <!-- Reply reference -->
            <div
                v-if="message.reply_to"
                class="mt-1 flex items-start gap-1.5 rounded border-l-2 border-primary/50 bg-accent/30 px-2 py-1.5 text-xs"
            >
                <CornerDownRight
                    :size="14"
                    class="mt-0.5 shrink-0 text-muted-foreground"
                />
                <div class="min-w-0 flex-1">
                    <span class="font-medium text-primary">
                        {{ message.reply_to.user.username }}
                    </span>
                    <span class="block truncate text-muted-foreground">
                        {{ message.reply_to.content.substring(0, 100)
                        }}{{
                            message.reply_to.content.length > 100 ? '...' : ''
                        }}
                    </span>
                </div>
            </div>

            <!-- Edit mode -->
            <div v-if="isEditing" class="mt-1">
                <textarea
                    :value="editContent"
                    rows="2"
                    class="w-full resize-none rounded border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    @input="
                        emit(
                            'updateEditContent',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                    @keydown="handleEditKeydown"
                />
                <div
                    class="mt-1 flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span
                        >escape to
                        <button
                            class="text-primary hover:underline"
                            @click="emit('cancelEdit')"
                        >
                            cancel
                        </button></span
                    >
                    <span
                        >• enter to
                        <button
                            class="text-primary hover:underline"
                            @click="emit('saveEdit')"
                        >
                            save
                        </button></span
                    >
                </div>
            </div>

            <!-- Normal display -->
            <div v-else class="mt-1">
                <!-- GIF display -->
                <div
                    v-if="isGifUrl"
                    class="max-w-sm overflow-hidden rounded-lg"
                >
                    <img
                        :src="message.content"
                        alt="GIF"
                        class="h-auto w-full"
                        loading="lazy"
                    />
                </div>

                <!-- Text content -->
                <div
                    v-else-if="messageWithoutYoutubeUrl && !youtubeVideoId"
                    class="text-sm wrap-break-word whitespace-pre-wrap"
                    v-html="renderedContent"
                />

                <template v-else-if="youtubeVideoId">
                    <div
                        v-if="messageWithoutYoutubeUrl"
                        class="mb-2 text-sm wrap-break-word whitespace-pre-wrap"
                        v-html="renderedContentWithoutYoutube"
                    />

                    <!-- YouTube embed -->
                    <div class="mt-2 max-w-md overflow-hidden rounded-lg">
                        <iframe
                            :src="`https://www.youtube.com/embed/${youtubeVideoId}`"
                            class="aspect-video w-full"
                            frameborder="0"
                            allow="
                                accelerometer;
                                autoplay;
                                clipboard-write;
                                encrypted-media;
                                gyroscope;
                                picture-in-picture;
                            "
                            allowfullscreen
                        />
                    </div>
                </template>
            </div>

            <!-- Reactions display -->
            <div
                v-if="message.reactions?.length"
                class="mt-1.5 flex flex-wrap gap-1"
            >
                <button
                    v-for="group in groupedReactions"
                    :key="group.emoji"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-colors"
                    :class="
                        group.userReacted
                            ? 'border-primary/50 bg-primary/10 text-primary'
                            : 'border-border bg-accent/50 text-muted-foreground hover:bg-accent'
                    "
                    @click="emit('toggleReaction', group.emoji)"
                >
                    <span>{{ group.emoji }}</span>
                    <span>{{ group.count }}</span>
                </button>
            </div>
        </div>

        <!-- Action buttons (hover) -->
        <div
            v-if="!isEditing"
            class="absolute -top-3 right-2 hidden gap-0.5 rounded border border-border bg-background p-0.5 shadow-sm group-hover:flex"
        >
            <button
                data-reaction-button
                class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                title="Add reaction"
                @click.stop="emit('toggleEmojiPicker')"
            >
                <SmilePlus :size="16" />
            </button>
            <button
                class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                title="Reply"
                @click="emit('reply')"
            >
                <CornerDownRight :size="16" />
            </button>
            <template v-if="message.user.id === currentUser.id">
                <button
                    class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    title="Edit message"
                    @click="emit('startEdit')"
                >
                    <Pencil :size="16" />
                </button>
                <button
                    class="rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                    title="Delete message"
                    @click="emit('delete')"
                >
                    <Trash2 :size="16" />
                </button>
            </template>
        </div>

        <!-- Emoji picker dropdown -->
        <EmojiPicker
            v-if="showEmojiPicker"
            class="emoji-picker-container absolute -top-3 right-20 z-10"
            @select="emit('toggleReaction', $event)"
        />
    </div>
</template>
