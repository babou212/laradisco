<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Search } from 'lucide-vue-next';
import { watch, ref } from 'vue'; // Import watch from vue
import { nextTick } from 'vue';
import { Input } from '@/components/ui/input';
import { useSearch } from '@/composables/useSearch';

const { query, search, isOpen } = useSearch();
const inputRef = ref<HTMLInputElement | null>(null);
const inputValue = ref(query.value);
const isSubmitting = ref(false);

const debouncedSearch = useDebounceFn(() => {
    search();
}, 500);

watch(inputValue, (newVal) => {
    if (isSubmitting.value) return;
    query.value = newVal;
    if (newVal.length > 2) {
        debouncedSearch();
    }
});

// If query is updated from elsewhere (e.g. clearing search), sync input
watch(query, (newVal) => {
    if (!isSubmitting.value && inputValue.value !== newVal) {
        inputValue.value = newVal;
    }
});

const handleEnter = () => {
    if (!inputValue.value) return;
    
    // Ensure search is triggered immediately
    query.value = inputValue.value;
    search();
    isOpen.value = true;
    
    // Clear input but keep query (via flag)
    isSubmitting.value = true;
    inputValue.value = '';
    
    nextTick(() => {
        isSubmitting.value = false;
        // Blur to close suggestions/keyboard if needed
        inputRef.value?.blur();
    });
};
</script>

<template>
    <div class="relative w-full max-w-sm items-center group">
        <Input 
            ref="inputRef"
            v-model="inputValue" 
            placeholder="Search messages..." 
            class="pl-10 h-8 text-sm pr-4 transition-all focus:w-full"
            @keydown.enter="handleEnter"
        />
        <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
            <Search class="size-4 text-muted-foreground" />
        </span>
        
        <!-- Helper Hints -->
        <div class="absolute top-full mt-2 w-full bg-popover text-popover-foreground shadow-md rounded-md border p-2 text-xs z-50 hidden group-focus-within:block" v-if="!inputValue">
            <p class="font-semibold mb-1">Search Filters:</p>
            <ul class="space-y-1 text-muted-foreground">
                <li><code class="bg-muted px-1 rounded">from:username</code> - Messages from user</li>
                <li><code class="bg-muted px-1 rounded">in:channel</code> - Messages in channel</li>
                <li><code class="bg-muted px-1 rounded">has:attachment</code> - With files</li>
            </ul>
        </div>
    </div>
</template>
