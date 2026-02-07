<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

</script>

<template>
    <SettingsLayout>
        <Head title="Appearance settings" />

        <h1 class="sr-only">Appearance Settings</h1>

        <div class="rounded-lg border bg-card">
            <div class="border-b bg-muted/50 px-6 py-4">
                <h2 class="text-lg font-semibold">Appearance</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Customize how your interface looks and feels
                </p>
            </div>
            
            <div class="p-6">
                <AppearanceTabs />
            </div>
        </div>
    </SettingsLayout>
</template>
