<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Dark-category themes that require the .dark class on the HTML element.
     *
     * @var string[]
     */
    private const DARK_THEMES = [
        'default-dark',
        'dracula',
        'nord-dark',
        'midnight',
        'cyberpunk',
        'monokai',
        'emerald',
        'solarized-dark',
        'crimson',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->user()?->theme ?? $request->cookie('theme') ?? 'default';

        View::share('theme', $theme);
        View::share('isDarkTheme', in_array($theme, self::DARK_THEMES, true));

        return $next($request);
    }
}
