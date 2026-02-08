<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Search } from 'lucide-vue-next';
import { watch, ref } from 'vue'; // Import watch from vue
import { nextTick } from 'vue';
import { Input } from '@/components/ui/input';
import { useSearch } from '@/composables/useSearch';

const { query, search, isOpen } = useSearch();
const inputRef = ref<any>(null);
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
        inputRef.value?.$el?.blur();
    });
};
</script>

<template>
    <div class="group relative w-full max-w-sm items-center">
        <Input
            ref="inputRef"
            v-model="inputValue"
            placeholder="Search messages..."
            class="h-8 pr-4 pl-10 text-sm transition-all focus:w-full"
            @keydown.enter="handleEnter"
        />
        <span
            class="absolute inset-y-0 start-0 flex items-center justify-center px-2"
        >
            <Search class="size-4 text-muted-foreground" />
        </span>

        <!-- Helper Hints -->
        <div
            class="absolute top-full z-50 mt-2 hidden w-full rounded-md border bg-popover p-2 text-xs text-popover-foreground shadow-md group-focus-within:block"
            v-if="!inputValue"
        >
            <p class="mb-1 font-semibold">Search Filters:</p>
            <ul class="space-y-1 text-muted-foreground">
                <li>
                    <code class="rounded bg-muted px-1">from:username</code> -
                    Messages from user
                </li>
                <li>
                    <code class="rounded bg-muted px-1">in:channel</code> -
                    Messages in channel
                </li>
                <li>
                    <code class="rounded bg-muted px-1">has:attachment</code> -
                    With files
                </li>
            </ul>
        </div>
    </div>
</template>
