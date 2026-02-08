<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { useSearch } from '@/composables/useSearch';
import { router } from '@inertiajs/vue3';
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
    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800 text-inherit px-0.5 rounded">$1</mark>');
};
</script>

<template>
    <div 
        v-if="isOpen"
        class="w-80 border-l border-sidebar-border bg-sidebar h-full flex flex-col shrink-0 transition-all duration-300"
    >
        <div class="h-12 border-b border-sidebar-border flex items-center justify-between px-4 shrink-0">
            <span class="font-semibold text-sm">Search Results</span>
            <button @click="close" class="text-muted-foreground hover:text-foreground">
                <X class="size-4" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <div v-if="isLoading" class="text-center py-4 text-muted-foreground text-sm">
                Searching...
            </div>
            
            <div v-else-if="results.length === 0" class="text-center py-4 text-muted-foreground text-sm">
                No results found.
            </div>

            <div 
                v-for="message in results" 
                :key="message.id" 
                @click="navigateToMessage(message)"
                class="p-3 bg-muted/30 rounded-lg text-sm group hover:bg-muted/50 transition-colors cursor-pointer border border-transparent hover:border-border"
            >
                <div class="flex items-center justify-between mb-1">
                    <span class="font-medium text-xs">{{ message.user?.name || message.user?.username }}</span>
                    <span class="text-[10px] text-muted-foreground">{{ formatMessageDate(message.created_at) }}</span>
                </div>
                <div class="text-muted-foreground line-clamp-3 break-words" v-html="highlightText(message.content)"></div>
                <!-- Channel Context -->
                <div v-if="message.channel" class="mt-2 text-[10px] text-muted-foreground/70 bg-background/50 inline-block px-1.5 py-0.5 rounded border border-border/50">
                    # {{ message.channel.name }}
                </div>
                <!-- DM Context -->
                <div v-else-if="message.group" class="mt-2 text-[10px] text-muted-foreground/70 bg-background/50 inline-block px-1.5 py-0.5 rounded border border-border/50">
                    DM
                </div>
            </div>
        </div>
    </div>
</template>
