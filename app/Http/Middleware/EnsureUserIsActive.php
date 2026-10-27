<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR1: a deactivated account must be unusable, not just un-loggable-in.
 * Deactivation happens in admin user management; without this check an
 * already-authenticated customer or driver could keep transacting until
 * their session expires.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user !== null && ! $user->is_active, Response::HTTP_FORBIDDEN, 'Your account has been deactivated.');

        return $next($request);
    }
}
