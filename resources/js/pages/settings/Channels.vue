<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Hash,
    Folder,
    Lock,
    Pencil,
    Plus,
    Trash2,
    Volume2,
    Shield,
    ChevronDown,
    ChevronRight,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    store as storeCategory,
    update as updateCategory,
    destroy as destroyCategory,
} from '@/routes/settings/categories';
import {
    store as storeChannel,
    update as updateChannel,
    destroy as destroyChannel,
} from '@/routes/settings/channels';
import {
    store as storeOverride,
    destroy as destroyOverride,
} from '@/routes/settings/channels/overrides';

type Permission = {
    value: string;
    label: string;
};

type Role = {
    id: number;
    name: string;
    color: string;
};

type Channel = {
    id: number;
    category_id: number | null;
    name: string;
    slug: string;
    topic: string | null;
    type: 'text' | 'voice';
    position: number;
    is_private: boolean;
    slowmode_seconds: number | null;
};

type Category = {
    id: number;
    name: string;
    position: number;
    channels: Channel[];
};

type ChannelOverride = {
    id: number;
    channel_id: number;
    role_id: number | null;
    user_id: number | null;
    allow: string[];
    deny: string[];
    role?: { id: number; name: string; color: string } | null;
    user?: { id: number; username: string; name: string } | null;
};

const props = defineProps<{
    categories: Category[];
    roles: Role[];
    permissions: Permission[];
}>();

const expandedCategories = ref<Set<number>>(
    new Set(props.categories.map((c) => c.id)),
);
const showCreateChannelDialog = ref(false);
const showEditChannelDialog = ref(false);
const showDeleteChannelDialog = ref(false);
const showCreateCategoryDialog = ref(false);
const showEditCategoryDialog = ref(false);
const showDeleteCategoryDialog = ref(false);
const showOverridesDialog = ref(false);

const editingChannel = ref<Channel | null>(null);
const deletingChannel = ref<Channel | null>(null);
const editingCategory = ref<Category | null>(null);
const deletingCategory = ref<Category | null>(null);
const overridesChannel = ref<Channel | null>(null);
const channelOverrides = ref<ChannelOverride[]>([]);
const loadingOverrides = ref(false);

const channelForm = useForm({
    category_id: null as number | null,
    name: '',
    topic: '',
    type: 'text' as 'text' | 'voice',
    position: 0,
    is_private: false,
    slowmode_seconds: 0,
});

const editChannelForm = useForm({
    category_id: null as number | null,
    name: '',
    topic: '',
    type: 'text' as 'text' | 'voice',
    position: 0,
    is_private: false,
    slowmode_seconds: 0,
});

const categoryForm = useForm({ name: '' });
const editCategoryForm = useForm({ name: '' });

const overrideForm = useForm({
    role_id: null as number | null,
    allow: [] as string[],
    deny: [] as string[],
});

function toggleCategory(id: number) {
    if (expandedCategories.value.has(id)) {
        expandedCategories.value.delete(id);
    } else {
        expandedCategories.value.add(id);
    }
}

function channelIcon(type: string) {
    if (type === 'voice') return Volume2;
    return Hash;
}

const availableOverrideRoles = computed(() => {
    const existingRoleIds = new Set(
        channelOverrides.value.filter((o) => o.role_id).map((o) => o.role_id),
    );
    return props.roles.filter((r) => !existingRoleIds.has(r.id));
});

// Channel CRUD
function openCreateChannel(categoryId: number | null = null) {
    channelForm.reset();
    channelForm.category_id = categoryId;
    showCreateChannelDialog.value = true;
}

function openEditChannel(channel: Channel) {
    editingChannel.value = channel;
    editChannelForm.category_id = channel.category_id;
    editChannelForm.name = channel.name;
    editChannelForm.topic = channel.topic ?? '';
    editChannelForm.type = channel.type;
    editChannelForm.position = channel.position;
    editChannelForm.is_private = channel.is_private;
    editChannelForm.slowmode_seconds = channel.slowmode_seconds ?? 0;
    showEditChannelDialog.value = true;
}

function openDeleteChannel(channel: Channel) {
    deletingChannel.value = channel;
    showDeleteChannelDialog.value = true;
}

function submitCreateChannel() {
    channelForm.post(storeChannel().url, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateChannelDialog.value = false;
            channelForm.reset();
        },
    });
}

function submitEditChannel() {
    if (!editingChannel.value) return;
    editChannelForm.put(updateChannel(editingChannel.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            showEditChannelDialog.value = false;
            editingChannel.value = null;
        },
    });
}

function confirmDeleteChannel() {
    if (!deletingChannel.value) return;
    router.delete(destroyChannel(deletingChannel.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteChannelDialog.value = false;
            deletingChannel.value = null;
        },
    });
}

// Category CRUD
function openCreateCategory() {
    categoryForm.reset();
    showCreateCategoryDialog.value = true;
}

function openEditCategory(category: Category) {
    editingCategory.value = category;
    editCategoryForm.name = category.name;
    showEditCategoryDialog.value = true;
}

function openDeleteCategory(category: Category) {
    deletingCategory.value = category;
    showDeleteCategoryDialog.value = true;
}

function submitCreateCategory() {
    categoryForm.post(storeCategory().url, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateCategoryDialog.value = false;
            categoryForm.reset();
        },
    });
}

function submitEditCategory() {
    if (!editingCategory.value) return;
    editCategoryForm.put(updateCategory(editingCategory.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            showEditCategoryDialog.value = false;
            editingCategory.value = null;
        },
    });
}

function confirmDeleteCategory() {
    if (!deletingCategory.value) return;
    router.delete(destroyCategory(deletingCategory.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteCategoryDialog.value = false;
            deletingCategory.value = null;
        },
    });
}

// Permission Overrides
async function openOverrides(channel: Channel) {
    overridesChannel.value = channel;
    loadingOverrides.value = true;
    showOverridesDialog.value = true;
    overrideForm.reset();

    try {
        const response = await fetch(
            `/settings/channels/${channel.id}/overrides`,
        );
        channelOverrides.value = await response.json();
    } finally {
        loadingOverrides.value = false;
    }
}

function submitOverride() {
    if (!overridesChannel.value) return;
    overrideForm.post(storeOverride(overridesChannel.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            overrideForm.reset();
            // Refresh overrides
            if (overridesChannel.value) {
                openOverrides(overridesChannel.value);
            }
        },
    });
}

function deleteOverride(override: ChannelOverride) {
    if (!overridesChannel.value) return;
    router.delete(
        destroyOverride({
            channel: overridesChannel.value.id,
            override: override.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (overridesChannel.value) {
                    openOverrides(overridesChannel.value);
                }
            },
        },
    );
}

function toggleOverridePermission(list: 'allow' | 'deny', permission: string) {
    const otherList = list === 'allow' ? 'deny' : 'allow';
    // Remove from the other list if present
    const otherIdx = overrideForm[otherList].indexOf(permission);
    if (otherIdx !== -1) {
        overrideForm[otherList].splice(otherIdx, 1);
    }

    const idx = overrideForm[list].indexOf(permission);
    if (idx === -1) {
        overrideForm[list].push(permission);
    } else {
        overrideForm[list].splice(idx, 1);
    }
}

function getPermissionLabel(value: string): string {
    return props.permissions.find((p) => p.value === value)?.label ?? value;
}

const channelPermissions = [
    'view_channels',
    'send_messages',
    'send_thread_messages',
    'create_threads',
    'embed_links',
    'attach_files',
    'add_reactions',
    'mention_everyone',
    'manage_messages',
    'manage_threads',
    'read_message_history',
    'pin_messages',
    'connect',
    'speak',
    'video',
];
</script>

<template>
    <SettingsLayout>
        <Head title="Channels" />

        <h1 class="sr-only">Channel Management</h1>

        <div class="rounded-lg border bg-card">
            <div
                class="flex items-center justify-between border-b bg-muted/50 px-6 py-4"
            >
                <div>
                    <h2 class="text-lg font-semibold">Channels</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Manage categories, channels, and per-channel permission
                        overrides.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        @click="openCreateCategory"
                        size="sm"
                        variant="outline"
                    >
                        <Folder class="mr-1.5 h-4 w-4" />
                        New Category
                    </Button>
                    <Button @click="openCreateChannel()" size="sm">
                        <Plus class="mr-1.5 h-4 w-4" />
                        New Channel
                    </Button>
                </div>
            </div>

            <div class="p-6">
                <div
                    v-if="categories.length === 0"
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <div
                        class="mb-3 rounded-full border border-border bg-muted p-3"
                    >
                        <Hash class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <p class="text-sm font-medium">No channels yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create a category and add channels to get started.
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="rounded-lg border border-border"
                    >
                        <!-- Category Header -->
                        <div
                            class="flex cursor-pointer items-center justify-between bg-muted/30 px-4 py-3"
                            @click="toggleCategory(category.id)"
                        >
                            <div class="flex items-center gap-2">
                                <component
                                    :is="
                                        expandedCategories.has(category.id)
                                            ? ChevronDown
                                            : ChevronRight
                                    "
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <span
                                    class="text-sm font-semibold tracking-wider uppercase"
                                    >{{ category.name }}</span
                                >
                                <span class="text-xs text-muted-foreground"
                                    >({{ category.channels.length }})</span
                                >
                            </div>
                            <div class="flex items-center gap-1" @click.stop>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7"
                                    @click="openCreateChannel(category.id)"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7"
                                    @click="openEditCategory(category)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-destructive hover:text-destructive"
                                    @click="openDeleteCategory(category)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>

                        <!-- Channels -->
                        <div
                            v-show="expandedCategories.has(category.id)"
                            class="divide-y"
                        >
                            <div
                                v-for="channel in category.channels"
                                :key="channel.id"
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="channelIcon(channel.type)"
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{
                                                channel.name
                                            }}</span>
                                            <Lock
                                                v-if="channel.is_private"
                                                class="h-3 w-3 text-muted-foreground"
                                            />
                                            <Badge
                                                v-if="channel.is_private"
                                                variant="secondary"
                                                class="text-xs"
                                                >Private</Badge
                                            >
                                        </div>
                                        <p
                                            v-if="channel.topic"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ channel.topic }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7"
                                        @click="openOverrides(channel)"
                                    >
                                        <Shield class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7"
                                        @click="openEditChannel(channel)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7 text-destructive hover:text-destructive"
                                        @click="openDeleteChannel(channel)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="category.channels.length === 0"
                                class="px-4 py-4 text-center text-xs text-muted-foreground"
                            >
                                No channels in this category.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Channel Dialog -->
        <Dialog v-model:open="showCreateChannelDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Channel</DialogTitle>
                    <DialogDescription
                        >Add a new channel to the server.</DialogDescription
                    >
                </DialogHeader>

                <form @submit.prevent="submitCreateChannel" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="ch-name">Name</Label>
                        <Input
                            id="ch-name"
                            v-model="channelForm.name"
                            placeholder="channel-name"
                        />
                        <p
                            v-if="channelForm.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ channelForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ch-type">Type</Label>
                        <select
                            id="ch-type"
                            v-model="channelForm.type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="text">Text</option>
                            <option value="voice">Voice</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ch-topic">Topic</Label>
                        <Input
                            id="ch-topic"
                            v-model="channelForm.topic"
                            placeholder="What's this channel about?"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ch-category">Category</Label>
                        <select
                            id="ch-category"
                            v-model="channelForm.category_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option :value="null">None</option>
                            <option
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="ch-private"
                            :model-value="channelForm.is_private"
                            @update:model-value="
                                channelForm.is_private = !!$event
                            "
                        />
                        <Label for="ch-private" class="text-sm"
                            >Private channel (requires explicit role/user
                            access)</Label
                        >
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateChannelDialog = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="channelForm.processing"
                            >Create Channel</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Channel Dialog -->
        <Dialog v-model:open="showEditChannelDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Channel</DialogTitle>
                    <DialogDescription
                        >Update channel settings.</DialogDescription
                    >
                </DialogHeader>

                <form @submit.prevent="submitEditChannel" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="ech-name">Name</Label>
                        <Input id="ech-name" v-model="editChannelForm.name" />
                        <p
                            v-if="editChannelForm.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ editChannelForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ech-type">Type</Label>
                        <select
                            id="ech-type"
                            v-model="editChannelForm.type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="text">Text</option>
                            <option value="voice">Voice</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ech-topic">Topic</Label>
                        <Input id="ech-topic" v-model="editChannelForm.topic" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ech-category">Category</Label>
                        <select
                            id="ech-category"
                            v-model="editChannelForm.category_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option :value="null">None</option>
                            <option
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ech-slowmode">Slowmode (seconds)</Label>
                        <Input
                            id="ech-slowmode"
                            type="number"
                            v-model.number="editChannelForm.slowmode_seconds"
                            min="0"
                            max="21600"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="ech-private"
                            :model-value="editChannelForm.is_private"
                            @update:model-value="
                                editChannelForm.is_private = !!$event
                            "
                        />
                        <Label for="ech-private" class="text-sm"
                            >Private channel</Label
                        >
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditChannelDialog = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="editChannelForm.processing"
                            >Save Changes</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Channel Dialog -->
        <Dialog v-model:open="showDeleteChannelDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Channel</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete
                        <strong>{{ deletingChannel?.name }}</strong
                        >? All messages in this channel will be lost. This
                        cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showDeleteChannelDialog = false"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmDeleteChannel"
                        >Delete Channel</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create Category Dialog -->
        <Dialog v-model:open="showCreateCategoryDialog">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Create Category</DialogTitle>
                    <DialogDescription
                        >Add a new category to organize
                        channels.</DialogDescription
                    >
                </DialogHeader>

                <form @submit.prevent="submitCreateCategory" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="cat-name">Name</Label>
                        <Input
                            id="cat-name"
                            v-model="categoryForm.name"
                            placeholder="Category name"
                        />
                        <p
                            v-if="categoryForm.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ categoryForm.errors.name }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateCategoryDialog = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="categoryForm.processing"
                            >Create</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Category Dialog -->
        <Dialog v-model:open="showEditCategoryDialog">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Edit Category</DialogTitle>
                    <DialogDescription
                        >Update the category name.</DialogDescription
                    >
                </DialogHeader>

                <form @submit.prevent="submitEditCategory" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="ecat-name">Name</Label>
                        <Input id="ecat-name" v-model="editCategoryForm.name" />
                        <p
                            v-if="editCategoryForm.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ editCategoryForm.errors.name }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditCategoryDialog = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="editCategoryForm.processing"
                            >Save</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Category Dialog -->
        <Dialog v-model:open="showDeleteCategoryDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Category</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete
                        <strong>{{ deletingCategory?.name }}</strong
                        >? All channels in this category will also be deleted.
                        This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showDeleteCategoryDialog = false"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmDeleteCategory"
                        >Delete Category</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Channel Permission Overrides Dialog -->
        <Dialog v-model:open="showOverridesDialog">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Permission Overrides</DialogTitle>
                    <DialogDescription>
                        Manage permission overrides for
                        <strong>#{{ overridesChannel?.name }}</strong
                        >. Overrides allow or deny specific permissions for
                        roles on this channel.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="loadingOverrides"
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    Loading...
                </div>

                <div v-else class="space-y-4">
                    <!-- Existing Overrides -->
                    <div v-if="channelOverrides.length > 0" class="space-y-2">
                        <h3 class="text-sm font-semibold">Current Overrides</h3>
                        <div
                            v-for="override in channelOverrides"
                            :key="override.id"
                            class="rounded-lg border border-border p-3"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        v-if="override.role"
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                override.role.color,
                                        }"
                                    />
                                    <span class="text-sm font-medium">
                                        {{
                                            override.role?.name ??
                                            override.user?.username ??
                                            'Unknown'
                                        }}
                                    </span>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ override.role ? 'Role' : 'User' }}
                                    </Badge>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-destructive hover:text-destructive"
                                    @click="deleteOverride(override)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <Badge
                                    v-for="perm in override.allow"
                                    :key="`allow-${perm}`"
                                    class="bg-green-500/10 text-xs text-green-600"
                                >
                                    + {{ getPermissionLabel(perm) }}
                                </Badge>
                                <Badge
                                    v-for="perm in override.deny"
                                    :key="`deny-${perm}`"
                                    variant="destructive"
                                    class="text-xs"
                                >
                                    - {{ getPermissionLabel(perm) }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Add Override -->
                    <div>
                        <h3 class="mb-3 text-sm font-semibold">Add Override</h3>
                        <form
                            @submit.prevent="submitOverride"
                            class="space-y-3"
                        >
                            <div class="grid gap-2">
                                <Label for="ovr-role">Role</Label>
                                <select
                                    id="ovr-role"
                                    v-model="overrideForm.role_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option :value="null">
                                        Select a role...
                                    </option>
                                    <option
                                        v-for="role in availableOverrideRoles"
                                        :key="role.id"
                                        :value="role.id"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <p
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Permissions
                                </p>
                                <div class="grid gap-1.5">
                                    <div
                                        v-for="perm in channelPermissions"
                                        :key="perm"
                                        class="flex items-center justify-between rounded px-2 py-1 text-sm"
                                    >
                                        <span>{{
                                            getPermissionLabel(perm)
                                        }}</span>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                :class="[
                                                    'rounded px-2 py-0.5 text-xs font-medium transition-colors',
                                                    overrideForm.allow.includes(
                                                        perm,
                                                    )
                                                        ? 'bg-green-500 text-white'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                                ]"
                                                @click="
                                                    toggleOverridePermission(
                                                        'allow',
                                                        perm,
                                                    )
                                                "
                                            >
                                                Allow
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'rounded px-2 py-0.5 text-xs font-medium transition-colors',
                                                    overrideForm.deny.includes(
                                                        perm,
                                                    )
                                                        ? 'bg-destructive text-destructive-foreground'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                                ]"
                                                @click="
                                                    toggleOverridePermission(
                                                        'deny',
                                                        perm,
                                                    )
                                                "
                                            >
                                                Deny
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    :disabled="
                                        overrideForm.processing ||
                                        !overrideForm.role_id
                                    "
                                    size="sm"
                                >
                                    Save Override
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </SettingsLayout>
</template>
