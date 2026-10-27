<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_FORBIDDEN);
        abort_if(! $user->is_active, Response::HTTP_FORBIDDEN, 'Your account has been deactivated.');

        if ($roles !== []) {
            // Strict: a route may only guard against real UserRole cases. A
            // typo'd role name here is a developer bug, so surface it loudly
            // rather than silently dropping it (which could widen access).
            $allowed = array_map(
                fn (string $role) => UserRole::from($role),
                $roles,
            );
            abort_unless($user->hasRole(...$allowed), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
