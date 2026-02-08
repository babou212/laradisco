<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { useSearch } from '@/composables/useSearch';
import { formatMessageDate } from '@/lib/utils'; // Use shared formatter

const { isOpen, results, isLoading, close, query } = useSearch();

const navigateToMessage = (message: any) => {
    if (message.channel_id) {
        router.visit(`/?channel=${message.channel_id}`, {
            preserveState: false, // Force refresh to ensure channel loads
            preserveScroll: true,
        });
        // In a real app, we would also emit an event to scroll to the message ID
    } else if (message.direct_message_group_id) {
        router.visit(`/direct-message/${message.direct_message_group_id}`, {
            preserveState: false,
            preserveScroll: true,
        });
    }
};

const highlightText = (text: string) => {
    if (!query.value) return text;
    // Extract actual search terms (removing filters)
    const cleanQuery = query.value
        .replace(/from:\w+/g, '')
        .replace(/in:[\w-]+/g, '')
        .replace(/has:attachment/g, '')
        .trim();

    if (!cleanQuery) return text;

    const regex = new RegExp(`(${cleanQuery})`, 'gi');
    return text.replace(
        regex,
        '<mark class="bg-yellow-200 dark:bg-yellow-800 text-inherit px-0.5 rounded">$1</mark>',
    );
};
</script>

<template>
    <div
        v-if="isOpen"
        class="flex h-full w-80 shrink-0 flex-col border-l border-sidebar-border bg-sidebar transition-all duration-300"
    >
        <div
            class="flex h-12 shrink-0 items-center justify-between border-b border-sidebar-border px-4"
        >
            <span class="text-sm font-semibold">Search Results</span>
            <button
                @click="close"
                class="text-muted-foreground hover:text-foreground"
            >
                <X class="size-4" />
            </button>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto p-4">
            <div
                v-if="isLoading"
                class="py-4 text-center text-sm text-muted-foreground"
            >
                Searching...
            </div>

            <div
                v-else-if="results.length === 0"
                class="py-4 text-center text-sm text-muted-foreground"
            >
                No results found.
            </div>

            <div
                v-for="message in results"
                :key="message.id"
                @click="navigateToMessage(message)"
                class="group cursor-pointer rounded-lg border border-transparent bg-muted/30 p-3 text-sm transition-colors hover:border-border hover:bg-muted/50"
            >
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-medium">{{
                        message.user?.name || message.user?.username
                    }}</span>
                    <span class="text-[10px] text-muted-foreground">{{
                        formatMessageDate(message.created_at)
                    }}</span>
                </div>
                <div
                    class="line-clamp-3 break-words text-muted-foreground"
                    v-html="highlightText(message.content)"
                ></div>
                <!-- Channel Context -->
                <div
                    v-if="message.channel"
                    class="mt-2 inline-block rounded border border-border/50 bg-background/50 px-1.5 py-0.5 text-[10px] text-muted-foreground/70"
                >
                    # {{ message.channel.name }}
                </div>
                <!-- DM Context -->
                <div
                    v-else-if="message.group"
                    class="mt-2 inline-block rounded border border-border/50 bg-background/50 px-1.5 py-0.5 text-[10px] text-muted-foreground/70"
                >
                    DM
                </div>
            </div>
        </div>
    </div>
</template>
