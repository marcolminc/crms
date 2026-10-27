<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsStaff;
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
