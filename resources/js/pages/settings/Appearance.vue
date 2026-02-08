<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ThemeSelector from '@/components/ThemeSelector.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { Theme } from '@/types';

defineProps<{
    currentTheme: Theme;
}>();
</script>

<template>
    <SettingsLayout>
        <Head title="Appearance settings" />

        <h1 class="sr-only">Appearance Settings</h1>

        <div class="space-y-6">
            <div class="rounded-lg border bg-card">
                <div class="border-b bg-muted/50 px-6 py-4">
                    <h2 class="text-lg font-semibold">Theme</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Select a theme to change the look and feel of the
                        application
                    </p>
                </div>

                <div class="p-6">
                    <ThemeSelector :current-theme="currentTheme" />
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
