<script setup lang="ts">
interface Props {
    count?: number;
}

withDefaults(defineProps<Props>(), {
    count: 5,
});
</script>

<template>
    <div class="space-y-0.5 px-2">
        <div
            v-for="i in count"
            :key="i"
            class="flex items-center gap-2 rounded px-2 py-1.5"
        >
            <div class="size-4 shrink-0 animate-pulse rounded bg-muted/50" />
            <div
                class="h-4 flex-1 animate-pulse rounded bg-muted/50"
                :style="{ width: `${60 + Math.random() * 30}%` }"
            />
        </div>
    </div>
</template>
