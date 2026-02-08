<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

defineProps<{
    user: {
        name: string;
        username: string;
        email: string;
    };
}>();
</script>

<template>
    <AuthBase
        title="Complete your setup"
        description="Update your account details and set a secure password to get started"
    >
        <Head title="Initial Setup" />

        <Form
            method="post"
            :action="`/setup`"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        name="name"
                        :default-value="user.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Your name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="your@email.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">New password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="New password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Complete Setup
                </Button>
            </div>
        </Form>

        <div
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30"
        >
            <div class="flex gap-3">
                <ShieldAlert
                    class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="text-sm text-amber-800 dark:text-amber-200">
                    <p class="font-medium">Enable Two-Factor Authentication</p>
                    <p class="mt-1 text-amber-700 dark:text-amber-300">
                        After completing setup, we strongly recommend enabling
                        2FA in your
                        <strong>Security Settings</strong> to protect your admin
                        account.
                    </p>
                </div>
            </div>
        </div>
    </AuthBase>
</template>
