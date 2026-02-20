<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    typingUsers: Map<
        number,
        { username: string; timeout: ReturnType<typeof setTimeout> }
    >;
}

const props = defineProps<Props>();

const typingUserNames = computed(() => {
    const names = Array.from(props.typingUsers.values()).map((u) => u.username);
    if (names.length === 0) return '';
    if (names.length === 1) return `${names[0]} is typing...`;
    return 'Several people are typing...';
});
</script>

<template>
    <div v-if="typingUserNames" class="px-4 pb-1 text-xs text-muted-foreground">
        <span class="inline-flex items-center gap-1">
            <span class="flex gap-0.5">
                <span
                    class="inline-block size-1.5 animate-bounce rounded-full bg-muted-foreground"
                    style="animation-delay: 0ms"
                />
                <span
                    class="inline-block size-1.5 animate-bounce rounded-full bg-muted-foreground"
                    style="animation-delay: 150ms"
                />
                <span
                    class="inline-block size-1.5 animate-bounce rounded-full bg-muted-foreground"
                    style="animation-delay: 300ms"
                />
            </span>
            {{ typingUserNames }}
        </span>
    </div>
</template>
