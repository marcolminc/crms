<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UC03 hardening: after a logout the browser Back button must not be able to
 * resurrect an authenticated page from cache. Authenticated responses are
 * marked uncacheable (no-store) so neither the HTTP cache nor the back/forward
 * cache keeps them; a Back navigation therefore re-requests, and with the
 * session gone the auth guard bounces the reader to the login screen. Public
 * (guest) responses are left cacheable on purpose.
 */
final class PreventCacheForAuthenticatedResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
