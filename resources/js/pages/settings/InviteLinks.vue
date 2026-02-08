<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, Link2, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { destroy, store } from '@/routes/invite-links';

type InviteLink = {
    id: number;
    token: string;
    expires_at: string;
    used_at: string | null;
    creator: { id: number; name: string; username: string } | null;
    used_by_user: { id: number; name: string; username: string } | null;
    created_at: string;
};

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps<{
    inviteLinks: InviteLink[];
}>();

const { copy, copied, text: copiedText } = useClipboard();

const baseUrl = computed(() => {
    return `${window.location.origin}/register?invite=`;
});

function generateLink() {
    router.post(store().url, {}, { preserveScroll: true });
}

function deleteLink(inviteLink: InviteLink) {
    router.delete(destroy(inviteLink).url, { preserveScroll: true });
}

function copyInviteUrl(token: string) {
    copy(baseUrl.value + token);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}

function isExpired(expiresAt: string): boolean {
    return new Date(expiresAt) < new Date();
}

function getStatus(link: InviteLink): 'used' | 'expired' | 'active' {
    if (link.used_at) {
        return 'used';
    }

    if (isExpired(link.expires_at)) {
        return 'expired';
    }

    return 'active';
}
</script>

<template>
    <SettingsLayout>
        <Head title="Invite Links" />

        <h1 class="sr-only">Invite Links</h1>

        <div class="rounded-lg border bg-card">
            <div
                class="flex items-center justify-between border-b bg-muted/50 px-6 py-4"
            >
                <div>
                    <h2 class="text-lg font-semibold">Invite Links</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Generate single-use invite links for new members. Links
                        expire after 1 hour.
                    </p>
                </div>
                <Button @click="generateLink" size="sm">
                    <Plus class="mr-1.5 h-4 w-4" />
                    Generate
                </Button>
            </div>

            <div class="p-6">
                <div
                    v-if="inviteLinks.length === 0"
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <div
                        class="mb-3 rounded-full border border-border bg-muted p-3"
                    >
                        <Link2 class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <p class="text-sm font-medium">No invite links yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Generate a link to invite someone to register.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="link in inviteLinks"
                        :key="link.id"
                        :class="[
                            'flex items-center justify-between gap-4 rounded-lg border p-4',
                            getStatus(link) === 'active'
                                ? 'border-border bg-background'
                                : 'border-border/50 bg-muted/30',
                        ]"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <code
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ baseUrl + link.token }}
                                </code>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                            >
                                <span
                                    >Created
                                    {{ formatDate(link.created_at) }}</span
                                >
                                <span>&middot;</span>
                                <span
                                    >Expires
                                    {{ formatDate(link.expires_at) }}</span
                                >
                                <template v-if="link.used_by_user">
                                    <span>&middot;</span>
                                    <span
                                        >Used by
                                        <strong>{{
                                            link.used_by_user.name
                                        }}</strong></span
                                    >
                                </template>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <Badge
                                :variant="
                                    getStatus(link) === 'active'
                                        ? 'default'
                                        : getStatus(link) === 'used'
                                          ? 'secondary'
                                          : 'destructive'
                                "
                            >
                                {{
                                    getStatus(link) === 'active'
                                        ? 'Active'
                                        : getStatus(link) === 'used'
                                          ? 'Used'
                                          : 'Expired'
                                }}
                            </Badge>

                            <Button
                                v-if="getStatus(link) === 'active'"
                                variant="ghost"
                                size="icon"
                                @click="copyInviteUrl(link.token)"
                                class="h-8 w-8"
                            >
                                <Check
                                    v-if="
                                        copied &&
                                        copiedText === baseUrl + link.token
                                    "
                                    class="h-4 w-4 text-green-500"
                                />
                                <Copy v-else class="h-4 w-4" />
                            </Button>

                            <Button
                                v-if="!link.used_at"
                                variant="ghost"
                                size="icon"
                                @click="deleteLink(link)"
                                class="h-8 w-8 text-destructive hover:text-destructive"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
