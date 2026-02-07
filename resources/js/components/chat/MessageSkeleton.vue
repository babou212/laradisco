<script setup lang="ts">
interface Props {
    count?: number;
}

withDefaults(defineProps<Props>(), {
    count: 3,
});
</script>

<template>
    <div class="space-y-4">
        <div v-for="i in count" :key="i" class="flex gap-3 p-2">
            <!-- Avatar skeleton -->
            <div class="size-10 shrink-0 animate-pulse rounded-full bg-muted" />

            <!-- Content skeleton -->
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <div class="h-3 w-24 animate-pulse rounded bg-muted" />
                    <div class="h-3 w-32 animate-pulse rounded bg-muted" />
                </div>
                <div class="space-y-1.5">
                    <div class="h-3 w-full animate-pulse rounded bg-muted" />
                    <div class="h-3 w-4/5 animate-pulse rounded bg-muted" />
                </div>
            </div>
        </div>
    </div>
</template>
