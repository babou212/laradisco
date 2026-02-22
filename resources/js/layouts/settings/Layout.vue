<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { dashboard } from '@/routes/index';
import { index as inviteLinksIndex } from '@/routes/invite-links';
import { edit as editNotifications } from '@/routes/notifications';
import { edit as editProfile } from '@/routes/profile';
import { index as channelsIndex } from '@/routes/settings/channels';
import { index as membersIndex } from '@/routes/settings/members';
import { index as rolesIndex } from '@/routes/settings/roles';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';

const page = usePage();
const canInviteMembers = computed(
    () => page.props.auth.permissions?.canInviteMembers ?? false,
);
const canManageRoles = computed(
    () => page.props.auth.permissions?.canManageRoles ?? false,
);
const canManageChannels = computed(
    () => page.props.auth.permissions?.canManageChannels ?? false,
);

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
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
        {
            title: 'Notifications',
            href: editNotifications(),
        },
    ];

    if (canInviteMembers.value) {
        items.push({
            title: 'Invite Links',
            href: inviteLinksIndex(),
        });
    }

    return items;
});

const adminNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (canManageRoles.value) {
        items.push({
            title: 'Roles',
            href: rolesIndex(),
        });
        items.push({
            title: 'Members',
            href: membersIndex(),
        });
    }

    if (canManageChannels.value) {
        items.push({
            title: 'Channels',
            href: channelsIndex(),
        });
    }

    return items;
});

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
                <aside class="w-full shrink-0 lg:w-56">
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
                                    <component
                                        :is="item.icon"
                                        class="h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                            </Button>
                        </nav>

                        <!-- Server Management Section -->
                        <template v-if="adminNavItems.length > 0">
                            <Separator class="my-1" />
                            <p
                                class="px-3 py-1.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Server
                            </p>
                            <nav
                                class="flex flex-col space-y-0.5"
                                aria-label="Server Settings"
                            >
                                <Button
                                    v-for="item in adminNavItems"
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
                                        <component
                                            :is="item.icon"
                                            class="h-4 w-4"
                                        />
                                        {{ item.title }}
                                    </Link>
                                </Button>
                            </nav>
                        </template>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="min-w-0 flex-1">
                    <div class="max-w-2xl space-y-6">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
