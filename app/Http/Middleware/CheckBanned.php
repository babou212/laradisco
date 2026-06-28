<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    /**
     * Reject requests from banned users.
     *
     * Returns a structured 403 carrying the ban reason and expiry so the client
     * can route to its banned screen and show how long the ban lasts, matching
     * the shape returned by AuthController when a banned user tries to log in.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            $ban = $user->activeBan();

            return response()->json([
                'code' => 'account_banned',
                'message' => 'Your account has been banned.',
                'reason' => $ban?->reason,
                'expires_at' => $ban?->expires_at?->format(DATE_ATOM),
                'permanent' => $ban?->expires_at === null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
