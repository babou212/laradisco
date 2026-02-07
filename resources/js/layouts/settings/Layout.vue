<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { dashboard } from '@/routes/index';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <div class="min-h-screen bg-background">
        <div class="container max-w-7xl px-4 py-6 md:py-8">
            <!-- Back Button -->
            <div class="mb-6">
                <Button variant="ghost" size="sm" as-child class="-ml-2">
                    <Link :href="dashboard().url">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Dashboard
                    </Link>
                </Button>
            </div>

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight">Settings</h1>
                <p class="mt-2 text-muted-foreground">
                    Manage your profile and account settings
                </p>
            </div>

            <!-- Main Content Layout -->
            <div class="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <!-- Sidebar Navigation -->
                <aside class="w-full lg:w-56 shrink-0">
                    <div class="rounded-lg border bg-card p-1">
                        <nav
                            class="flex flex-col space-y-0.5"
                            aria-label="Settings"
                        >
                            <Button
                                v-for="item in sidebarNavItems"
                                :key="toUrl(item.href)"
                                variant="ghost"
                                :class="[
                                    'justify-start font-medium transition-colors',
                                    isCurrentUrl(item.href)
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                as-child
                            >
                                <Link :href="item.href">
                                    <component :is="item.icon" class="h-4 w-4" />
                                    {{ item.title }}
                                </Link>
                            </Button>
                        </nav>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="flex-1 min-w-0">
                    <div class="max-w-2xl space-y-6">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
