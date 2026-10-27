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
            $allowed = array_filter($roles, fn (string $role) => UserRole::tryFrom($role) !== null);
            abort_unless($user->hasRole(...array_map(fn (string $r) => UserRole::from($r), $allowed)), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
