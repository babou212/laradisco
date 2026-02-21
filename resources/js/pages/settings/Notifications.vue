<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Settings/NotificationController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type NotificationPreferences = {
    enable_toast_notifications: boolean;
    enable_browser_notifications: boolean;
    enable_dm_notifications: boolean;
    enable_mention_notifications: boolean;
};

const props = defineProps<{
    preferences: NotificationPreferences;
}>();

const form = useForm<NotificationPreferences>({
    enable_toast_notifications: props.preferences.enable_toast_notifications,
    enable_browser_notifications:
        props.preferences.enable_browser_notifications,
    enable_dm_notifications: props.preferences.enable_dm_notifications,
    enable_mention_notifications:
        props.preferences.enable_mention_notifications,
});

function submit() {
    form.patch(update().url);
}
</script>

<template>
    <SettingsLayout>
        <Head title="Notification settings" />

        <h1 class="sr-only">Notification Settings</h1>

        <div class="rounded-lg border bg-card">
            <div class="border-b bg-muted/50 px-6 py-4">
                <h2 class="text-lg font-semibold">Notifications</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Control how and when you receive notifications
                </p>
            </div>

            <div class="p-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="space-y-4">
                        <h3 class="text-sm font-medium">Notification Types</h3>

                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="enable_mention_notifications"
                                :model-value="form.enable_mention_notifications"
                                @update:model-value="
                                    form.enable_mention_notifications = !!$event
                                "
                            />
                            <div class="space-y-1">
                                <Label
                                    for="enable_mention_notifications"
                                    class="cursor-pointer"
                                >
                                    Mention notifications
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Get notified when someone mentions you with
                                    @username, @everyone, or @here
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="enable_dm_notifications"
                                :model-value="form.enable_dm_notifications"
                                @update:model-value="
                                    form.enable_dm_notifications = !!$event
                                "
                            />
                            <div class="space-y-1">
                                <Label
                                    for="enable_dm_notifications"
                                    class="cursor-pointer"
                                >
                                    Direct message notifications
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Get notified when someone sends you a direct
                                    message
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <div class="space-y-4">
                            <h3 class="text-sm font-medium">
                                Delivery Methods
                            </h3>

                            <div class="flex items-start gap-3">
                                <Checkbox
                                    id="enable_toast_notifications"
                                    :model-value="
                                        form.enable_toast_notifications
                                    "
                                    @update:model-value="
                                        form.enable_toast_notifications =
                                            !!$event
                                    "
                                />
                                <div class="space-y-1">
                                    <Label
                                        for="enable_toast_notifications"
                                        class="cursor-pointer"
                                    >
                                        In-app pop-ups
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        Show toast notifications in the corner
                                        of the screen when the tab is active
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Checkbox
                                    id="enable_browser_notifications"
                                    :model-value="
                                        form.enable_browser_notifications
                                    "
                                    @update:model-value="
                                        form.enable_browser_notifications =
                                            !!$event
                                    "
                                />
                                <div class="space-y-1">
                                    <Label
                                        for="enable_browser_notifications"
                                        class="cursor-pointer"
                                    >
                                        Browser notifications
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        Show desktop notifications when the tab
                                        is in the background
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 border-t pt-6">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.isDirty"
                        >
                            Save preferences
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="form.recentlySuccessful"
                                class="text-sm font-medium text-green-600 dark:text-green-500"
                            >
                                Saved successfully
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </SettingsLayout>
</template>
