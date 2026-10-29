<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\PreventCacheForAuthenticatedResponses;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind nginx and tunnels (ngrok), TLS terminates upstream and the real
        // scheme/host arrive via X-Forwarded-* headers. Trusting them keeps
        // generated URLs https-correct; without it, assets are emitted as
        // http:// and browsers block them as mixed content.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // UC03: authenticated pages must not survive in the browser cache, so a
        // Back navigation after logout re-requests and hits the auth redirect.
        // Appended to the web group (runs after the session is started) and only
        // stamps headers when $request->user() is present, leaving public pages
        // cacheable.
        $middleware->web(append: [
            PreventCacheForAuthenticatedResponses::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'staff' => EnsureUserIsStaff::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'webhooks/azampay',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
