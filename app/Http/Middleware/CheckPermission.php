<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Uses Spatie's hasPermissionTo under the hood.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_FORBIDDEN, 'Authentication required.');
        }

        if ($user->isAdministrator()) {
            return $next($request);
        }

        foreach ($permissions as $permissionName) {
            if ($user->hasPermissionTo($permissionName)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'You do not have the required permissions.');
    }
}
