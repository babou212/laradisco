import { createInertiaApp } from '@inertiajs/vue3';
import Aura from '@primeuix/themes/aura';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';
import './lib/echo';
import { useNotificationStore } from './stores/notifications';
import { usePresenceStore } from './stores/presence';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pinia = createPinia();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.dark',
                    },
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

const presenceStore = usePresenceStore(pinia);
presenceStore.connect();

const notificationStore = useNotificationStore(pinia);
const pageProps = document.querySelector('#app')?.getAttribute('data-page');
if (pageProps) {
    try {
        const parsed = JSON.parse(pageProps);
        if (parsed?.props?.auth?.user?.id) {
            notificationStore.connect(parsed.props.auth.user.id);
            notificationStore.requestPermission();
        }
    } catch {
        // Not on an authenticated page
    }
}
