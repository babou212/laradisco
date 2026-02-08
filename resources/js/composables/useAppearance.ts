import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { Theme } from '@/types';

export type { Theme };

const DARK_THEMES: ReadonlySet<Theme> = new Set([
    'default-dark',
    'dracula',
    'nord-dark',
    'midnight',
    'cyberpunk',
    'monokai',
    'emerald',
    'solarized-dark',
    'crimson',
]);

export function isDarkTheme(theme: Theme): boolean {
    return DARK_THEMES.has(theme);
}

export type UseAppearanceReturn = {
    theme: Ref<Theme>;
    updateThemeLocally: (value: Theme) => void;
};

export function applyTheme(value: Theme): void {
    if (typeof document === 'undefined') {
        return;
    }

    // Set or remove the data-theme attribute...
    if (value === 'default' || value === 'default-dark') {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', value);
    }

    // Toggle .dark class based on theme category...
    document.documentElement.classList.toggle('dark', isDarkTheme(value));
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredTheme = (): Theme | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('theme') as Theme | null;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedTheme = getStoredTheme();
    applyTheme(savedTheme || 'default');
}

const theme = ref<Theme>('default');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedTheme = localStorage.getItem('theme') as Theme | null;

        if (savedTheme) {
            theme.value = savedTheme;
        }
    });

    function updateThemeLocally(value: Theme) {
        theme.value = value;

        // Store in localStorage for instant client-side persistence...
        localStorage.setItem('theme', value);

        // Store in cookie for SSR...
        setCookie('theme', value);

        applyTheme(value);
    }

    return {
        theme,
        updateThemeLocally,
    };
}
