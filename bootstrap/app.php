<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every PaaS terminates TLS at its own proxy and forwards the original
        // scheme in a header. Untrusted, Laravel sees plain HTTP and generates
        // http:// for canonical links, the sitemap, Open Graph tags and the
        // gateway's callback URL — on a site that is only reachable over https.
        //
        // Trusting all proxies is the right call where the container has no
        // public address of its own and every request arrives through the
        // platform's load balancer, whose addresses are not fixed.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        // Webhooks arrive from the gateway, not from a browser session, and
        // are authenticated by their signature instead of a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
