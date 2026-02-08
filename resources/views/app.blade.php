<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($isDarkTheme ?? false)]) @if(($theme ?? 'default') !== 'default' && ($theme ?? 'default') !== 'default-dark') data-theme="{{ $theme }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inject Reverb WebSocket config for runtime use by Laravel Echo --}}
        <script>
            window.__reverb = {!! json_encode([
                'key' => config('reverb.apps.apps.0.key', env('REVERB_APP_KEY', '')),
                'host' => config('reverb.apps.apps.0.options.host', env('REVERB_HOST', 'localhost')),
                'port' => (string) config('reverb.apps.apps.0.options.port', env('REVERB_PORT', '443')),
                'scheme' => config('reverb.apps.apps.0.options.scheme', env('REVERB_SCHEME', 'https')),
            ]) !!};
        </script>

        {{-- Inline script to apply theme immediately and prevent flash --}}
        <script>
            (function() {
                var darkThemes = ['default-dark','dracula','nord-dark','midnight','cyberpunk','monokai','emerald','solarized-dark','crimson'];
                var theme = localStorage.getItem('theme') || '{{ $theme ?? "default" }}';

                if (theme && theme !== 'default' && theme !== 'default-dark') {
                    document.documentElement.setAttribute('data-theme', theme);
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }

                if (darkThemes.indexOf(theme) !== -1) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
