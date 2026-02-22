<?php

namespace App\Http\Middleware;

use App\Enums\PermissionFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
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

        foreach ($permissions as $permissionValue) {
            $permission = PermissionFlag::tryFrom($permissionValue);

            if ($permission && $user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'You do not have the required permissions.');
    }
}
