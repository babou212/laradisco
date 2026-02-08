import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
window.Pusher = Pusher;

/**
 * Resolve Echo/Reverb configuration.
 *
 * Priority:
 * 1. Server-injected window.__reverb (set in app.blade.php from PHP config)
 *    — always correct for the current environment.
 * 2. Vite build-time VITE_REVERB_* env vars (fallback for dev/SSR).
 */
function resolveReverbConfig() {
    // Server-injected config (set in app.blade.php from reverb config)
    const serverConfig = (window as unknown as Record<string, unknown>)
        .__reverb as
        | { key?: string; host?: string; port?: string; scheme?: string }
        | undefined;

    // Values from Vite build (fallback)
    const viteKey = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
    const viteHost = import.meta.env.VITE_REVERB_HOST as string | undefined;
    const vitePort = import.meta.env.VITE_REVERB_PORT as string | undefined;
    const viteScheme = import.meta.env.VITE_REVERB_SCHEME as string | undefined;

    const key = serverConfig?.key || viteKey || '';
    const host = serverConfig?.host || viteHost || 'localhost';
    const port = Number(serverConfig?.port || vitePort || 8080);
    const scheme = serverConfig?.scheme || viteScheme || 'http';
    const forceTLS = scheme === 'https';

    return { key, host, port, forceTLS };
}

const config = resolveReverbConfig();

// Initialize Laravel Echo with Reverb configuration
export const echo = new Echo({
    broadcaster: 'reverb',
    key: config.key,
    wsHost: config.host,
    wsPort: config.forceTLS ? undefined : config.port,
    wssPort: config.forceTLS ? config.port : undefined,
    forceTLS: config.forceTLS,
    enabledTransports: config.forceTLS ? ['wss'] : ['ws'],
    disableStats: true,
    authEndpoint: '/broadcasting/auth',
});

export default echo;
