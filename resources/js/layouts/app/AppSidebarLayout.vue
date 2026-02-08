<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import SearchInput from '@/components/SearchInput.vue';
import SearchResultsSidebar from '@/components/SearchResultsSidebar.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="flex flex-col overflow-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs">
                <template #actions>
                    <SearchInput />
                </template>
            </AppSidebarHeader>
            <div class="flex min-h-0 flex-1 overflow-hidden">
                <main class="min-h-0 flex-1 overflow-y-auto">
                    <slot />
                </main>
                <SearchResultsSidebar />
            </div>
        </AppContent>
    </AppShell>
</template>
