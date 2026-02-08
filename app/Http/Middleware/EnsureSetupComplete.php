<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Routes that should be accessible even when setup is required.
     */
    protected array $except = [
        'setup',
        'setup.complete',
        'logout',
    ];

    /**
     * Handle an incoming request.
     *
     * Redirect users with must_setup=true to the initial setup page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_setup && ! $this->isExcluded($request)) {
            return redirect()->route('setup');
        }

        return $next($request);
    }

    /**
     * Determine if the request route is in the exclusion list.
     */
    protected function isExcluded(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return $routeName && in_array($routeName, $this->except);
    }
}
