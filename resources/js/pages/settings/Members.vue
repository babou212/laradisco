<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, Shield, UsersRound, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { assignRole, removeRole } from '@/routes/settings/members';

type Role = {
    id: number;
    name: string;
    color: string;
    position: number;
    is_default: boolean;
};

type MemberRole = {
    id: number;
    name: string;
    color: string;
    position: number;
};

type Member = {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar_path: string | null;
    display_name: string;
    roles: MemberRole[];
};

const props = defineProps<{
    members: Member[];
    roles: Role[];
}>();

const searchQuery = ref('');
const showRoleDialog = ref(false);
const showRemoveRoleDialog = ref(false);
const selectedMember = ref<Member | null>(null);
const removingRole = ref<MemberRole | null>(null);

const filteredMembers = computed(() => {
    if (!searchQuery.value) return props.members;
    const q = searchQuery.value.toLowerCase();
    return props.members.filter(
        (m) =>
            m.username.toLowerCase().includes(q) ||
            m.name.toLowerCase().includes(q) ||
            m.email.toLowerCase().includes(q),
    );
});

function openAssignRoleDialog(member: Member) {
    selectedMember.value = member;
    showRoleDialog.value = true;
}

function openRemoveRoleDialog(member: Member, role: MemberRole) {
    selectedMember.value = member;
    removingRole.value = role;
    showRemoveRoleDialog.value = true;
}

function doAssignRole(roleId: number) {
    if (!selectedMember.value) return;
    router.post(
        assignRole(selectedMember.value).url,
        { role_id: roleId },
        {
            preserveScroll: true,
            onSuccess: () => {
                showRoleDialog.value = false;
                selectedMember.value = null;
            },
        },
    );
}

function confirmRemoveRole() {
    if (!selectedMember.value || !removingRole.value) return;
    router.delete(removeRole(selectedMember.value).url, {
        data: { role_id: removingRole.value.id },
        preserveScroll: true,
        onSuccess: () => {
            showRemoveRoleDialog.value = false;
            selectedMember.value = null;
            removingRole.value = null;
        },
    });
}

function getAvailableRoles(member: Member): Role[] {
    const memberRoleIds = new Set(member.roles.map((r) => r.id));
    return props.roles.filter((r) => !memberRoleIds.has(r.id) && !r.is_default);
}
</script>

<template>
    <SettingsLayout>
        <Head title="Members" />

        <h1 class="sr-only">Members Management</h1>

        <div class="rounded-lg border bg-card">
            <div class="border-b bg-muted/50 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold">Members</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Manage member roles. Assign or remove roles to control
                        permissions.
                    </p>
                </div>
            </div>

            <div class="p-6">
                <div class="relative mb-4">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search members..."
                        class="pl-9"
                    />
                </div>

                <div
                    v-if="filteredMembers.length === 0"
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <div
                        class="mb-3 rounded-full border border-border bg-muted p-3"
                    >
                        <UsersRound class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <p class="text-sm font-medium">No members found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            searchQuery
                                ? 'Try a different search term.'
                                : 'No members registered yet.'
                        }}
                    </p>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="member in filteredMembers"
                        :key="member.id"
                        class="flex items-center justify-between gap-4 rounded-lg border border-border bg-background p-4"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium"
                                >
                                    {{
                                        member.display_name
                                            .charAt(0)
                                            .toUpperCase()
                                    }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="truncate text-sm font-medium"
                                            >{{ member.display_name }}</span
                                        >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >@{{ member.username }}</span
                                        >
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <Badge
                                            v-for="role in member.roles"
                                            :key="role.id"
                                            variant="outline"
                                            class="cursor-pointer gap-1 text-xs hover:bg-destructive/10"
                                            @click="
                                                openRemoveRoleDialog(
                                                    member,
                                                    role,
                                                )
                                            "
                                        >
                                            <div
                                                class="h-2 w-2 rounded-full"
                                                :style="{
                                                    backgroundColor: role.color,
                                                }"
                                            />
                                            {{ role.name }}
                                            <X
                                                v-if="role.name !== 'everyone'"
                                                class="h-3 w-3"
                                            />
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            @click="openAssignRoleDialog(member)"
                        >
                            <Shield class="mr-1.5 h-3.5 w-3.5" />
                            Add Role
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showRoleDialog">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Assign Role</DialogTitle>
                    <DialogDescription>
                        Select a role to assign to
                        <strong>{{ selectedMember?.display_name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <div
                        v-if="
                            selectedMember &&
                            getAvailableRoles(selectedMember).length === 0
                        "
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        This member already has all available roles.
                    </div>
                    <Button
                        v-for="role in selectedMember
                            ? getAvailableRoles(selectedMember)
                            : []"
                        :key="role.id"
                        variant="outline"
                        class="w-full justify-start gap-3"
                        @click="doAssignRole(role.id)"
                    >
                        <div
                            class="h-3 w-3 rounded-full"
                            :style="{ backgroundColor: role.color }"
                        />
                        {{ role.name }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showRemoveRoleDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Remove Role</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove the role
                        <strong>{{ removingRole?.name }}</strong> from
                        <strong>{{ selectedMember?.display_name }}</strong
                        >?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showRemoveRoleDialog = false"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmRemoveRole"
                        >Remove Role</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SettingsLayout>
</template>
