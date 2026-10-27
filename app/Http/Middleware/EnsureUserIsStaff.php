<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_FORBIDDEN);
        abort_if(! $user->is_active, Response::HTTP_FORBIDDEN, 'Your account has been deactivated.');
        abort_unless($user->isStaff(), Response::HTTP_FORBIDDEN, 'Staff access only.');

        return $next($request);
    }
}
