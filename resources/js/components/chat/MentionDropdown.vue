<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

export interface MentionUser {
    id: number;
    username: string;
    name: string;
    nickname: string | null;
    avatar_path: string | null;
}

interface Props {
    query: string;
    visible: boolean;
}

interface Emits {
    (e: 'select', value: string): void;
    (e: 'close'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const users = ref<MentionUser[]>([]);
const loading = ref(false);
const selectedIndex = ref(0);
const dropdownRef = ref<HTMLElement>();

const specialMentions = computed(() => {
    const items: Array<{ label: string; value: string; description: string }> =
        [];
    if (
        'everyone'.startsWith(props.query.toLowerCase()) ||
        props.query === ''
    ) {
        items.push({
            label: '@everyone',
            value: 'everyone',
            description: 'Notify all members in this server',
        });
    }
    if ('here'.startsWith(props.query.toLowerCase()) || props.query === '') {
        items.push({
            label: '@here',
            value: 'here',
            description: 'Notify online members in this channel',
        });
    }
    return items;
});

const allItems = computed(() => {
    const specials = specialMentions.value.map((s) => ({
        type: 'special' as const,
        ...s,
    }));
    const userItems = users.value.map((u) => ({
        type: 'user' as const,
        label: `@${u.username}`,
        value: u.username,
        description: u.nickname || u.name,
        avatar_path: u.avatar_path,
    }));
    return [...specials, ...userItems];
});

let abortController: AbortController | null = null;

const searchUsers = async (query: string) => {
    if (abortController) {
        abortController.abort();
    }

    if (!query || query.length < 1) {
        users.value = [];
        return;
    }

    // Don't search if it matches a special mention exactly
    if (['everyone', 'here'].includes(query.toLowerCase())) {
        users.value = [];
        return;
    }

    loading.value = true;
    abortController = new AbortController();

    try {
        const response = await fetch(
            `/api/mentions/search?q=${encodeURIComponent(query)}`,
            {
                signal: abortController.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (response.ok) {
            const data = await response.json();
            users.value = data.users;
        }
    } catch (err: unknown) {
        if (err instanceof Error && err.name !== 'AbortError') {
            console.error('Mention search failed:', err);
        }
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.query,
    (newQuery) => {
        selectedIndex.value = 0;
        searchUsers(newQuery);
    },
);

watch(
    () => props.visible,
    (isVisible) => {
        if (isVisible) {
            selectedIndex.value = 0;
            searchUsers(props.query);
        }
    },
);

const handleKeydown = (e: KeyboardEvent) => {
    if (!props.visible || allItems.value.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = (selectedIndex.value + 1) % allItems.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value =
            (selectedIndex.value - 1 + allItems.value.length) %
            allItems.value.length;
    } else if (e.key === 'Enter' || e.key === 'Tab') {
        if (allItems.value.length > 0) {
            e.preventDefault();
            const item = allItems.value[selectedIndex.value];
            if (item) {
                emit('select', item.value);
            }
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        emit('close');
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown, true);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown, true);
    if (abortController) {
        abortController.abort();
    }
});
</script>

<template>
    <div
        v-if="visible && allItems.length > 0"
        ref="dropdownRef"
        class="absolute bottom-full left-0 z-50 mb-1 w-72 overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
    >
        <div class="max-h-60 overflow-y-auto p-1">
            <div
                class="px-2 py-1.5 text-xs font-semibold text-muted-foreground"
            >
                Mentions
            </div>

            <button
                v-for="(item, index) in allItems"
                :key="item.value"
                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                :class="
                    index === selectedIndex
                        ? 'bg-accent text-accent-foreground'
                        : 'text-foreground hover:bg-accent/50'
                "
                @mouseenter="selectedIndex = index"
                @click.prevent="emit('select', item.value)"
            >
                <!-- Special mention icon -->
                <template v-if="item.type === 'special'">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        @
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ item.label }}</div>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ item.description }}
                        </div>
                    </div>
                </template>

                <!-- User mention -->
                <template v-else>
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                    >
                        {{ item.value[0]?.toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ item.label }}</div>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ item.description }}
                        </div>
                    </div>
                </template>
            </button>

            <div
                v-if="loading"
                class="flex items-center justify-center py-2 text-xs text-muted-foreground"
            >
                Searching...
            </div>
        </div>
    </div>
</template>
