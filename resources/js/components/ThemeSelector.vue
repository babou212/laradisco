<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { update as updateAppearanceRoute } from '@/routes/appearance';
import type { Theme } from '@/types';

const props = defineProps<{
    currentTheme: Theme;
}>();

const { theme, updateThemeLocally } = useAppearance();

type ThemeOption = { value: Theme; label: string; color: string; description: string };

const lightThemes: ThemeOption[] = [
    { value: 'default', label: 'Default', color: 'bg-neutral-500', description: 'Clean neutral grays' },
    { value: 'nord', label: 'Nord', color: 'bg-[hsl(213,32%,52%)]', description: 'Cool arctic blues' },
    { value: 'rose', label: 'Rose', color: 'bg-[hsl(347,77%,50%)]', description: 'Warm elegant pinks' },
    { value: 'ocean', label: 'Ocean', color: 'bg-[hsl(199,89%,42%)]', description: 'Deep sea blues' },
    { value: 'forest', label: 'Forest', color: 'bg-[hsl(142,50%,36%)]', description: 'Earthy greens' },
    { value: 'sunset', label: 'Sunset', color: 'bg-[hsl(25,95%,50%)]', description: 'Warm golden tones' },
    { value: 'lavender', label: 'Lavender', color: 'bg-[hsl(271,60%,58%)]', description: 'Soft purple pastels' },
    { value: 'solarized-light', label: 'Solarized', color: 'bg-[hsl(18,89%,44%)]', description: 'Classic warm ivory' },
];

const darkThemes: ThemeOption[] = [
    { value: 'default-dark', label: 'Default Dark', color: 'bg-neutral-400', description: 'Clean dark neutrals' },
    { value: 'dracula', label: 'Dracula', color: 'bg-[hsl(265,89%,68%)]', description: 'Bold purples & pinks' },
    { value: 'nord-dark', label: 'Nord Dark', color: 'bg-[hsl(213,32%,60%)]', description: 'Arctic dark blues' },
    { value: 'midnight', label: 'Midnight', color: 'bg-[hsl(217,92%,60%)]', description: 'Deep navy blue' },
    { value: 'cyberpunk', label: 'Cyberpunk', color: 'bg-[hsl(330,100%,60%)]', description: 'Neon pink & cyan' },
    { value: 'monokai', label: 'Monokai', color: 'bg-[hsl(80,76%,53%)]', description: 'Classic editor feel' },
    { value: 'emerald', label: 'Emerald', color: 'bg-[hsl(160,84%,39%)]', description: 'Rich dark greens' },
    { value: 'solarized-dark', label: 'Solarized Dark', color: 'bg-[hsl(18,89%,50%)]', description: 'Classic dark tones' },
    { value: 'crimson', label: 'Crimson', color: 'bg-[hsl(348,83%,47%)]', description: 'Intense dark reds' },
];

const form = useForm({
    theme: props.currentTheme,
});

function selectTheme(value: Theme) {
    updateThemeLocally(value);

    form.theme = value;
    form.patch(updateAppearanceRoute().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <div class="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Sun class="h-4 w-4" />
                Light Themes
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <button
                    v-for="item in lightThemes"
                    :key="item.value"
                    @click="selectTheme(item.value)"
                    :class="[
                        'group relative flex flex-col items-center gap-2.5 rounded-lg border-2 p-4 transition-all',
                        theme === item.value
                            ? 'border-primary bg-primary/5 shadow-sm'
                            : 'border-border hover:border-primary/40 hover:bg-accent',
                    ]"
                >
                    <div
                        :class="[
                            'h-8 w-8 rounded-full ring-2 ring-offset-2 ring-offset-background transition-shadow',
                            item.color,
                            theme === item.value
                                ? 'ring-primary'
                                : 'ring-transparent group-hover:ring-primary/30',
                        ]"
                    />
                    <div class="text-center">
                        <span class="text-sm font-medium">{{ item.label }}</span>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ item.description }}</p>
                    </div>
                    <div
                        v-if="theme === item.value"
                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                        <Check class="h-3 w-3" />
                    </div>
                </button>
            </div>
        </div>

        <div>
            <div class="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Moon class="h-4 w-4" />
                Dark Themes
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <button
                    v-for="item in darkThemes"
                    :key="item.value"
                    @click="selectTheme(item.value)"
                    :class="[
                        'group relative flex flex-col items-center gap-2.5 rounded-lg border-2 p-4 transition-all',
                        theme === item.value
                            ? 'border-primary bg-primary/5 shadow-sm'
                            : 'border-border hover:border-primary/40 hover:bg-accent',
                    ]"
                >
                    <div
                        :class="[
                            'h-8 w-8 rounded-full ring-2 ring-offset-2 ring-offset-background transition-shadow',
                            item.color,
                            theme === item.value
                                ? 'ring-primary'
                                : 'ring-transparent group-hover:ring-primary/30',
                        ]"
                    />
                    <div class="text-center">
                        <span class="text-sm font-medium">{{ item.label }}</span>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ item.description }}</p>
                    </div>
                    <div
                        v-if="theme === item.value"
                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                    >
                        <Check class="h-3 w-3" />
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
