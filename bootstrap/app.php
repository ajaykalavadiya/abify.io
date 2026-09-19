<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Public tracking endpoints (embed script + ingestion API) sit
            // outside the 'web' group entirely — no session/CSRF needed for
            // cross-origin <script>/sendBeacon/fetch(keepalive) calls.
            Route::middleware(['throttle:tracking', SubstituteBindings::class])
                ->group(base_path('routes/tracking.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Stripe's servers POST here with no session/CSRF token — Cashier's
        // signature verification (STRIPE_WEBHOOK_SECRET) is the real
        // authenticity check for this route.
        $middleware->validateCsrfTokens(except: ['stripe/*', 'shopify/webhook']);

        // Ingestion payloads are captured data, not user input, and must
        // arrive byte-for-byte as the browser sent them.
        //
        // Both of these run globally and rewrite every string in the request.
        // On a session replay that is destructive: the whitespace-only text
        // nodes between HTML elements trim to '' and are then converted to
        // null, and rrweb renders a null text node as the literal word
        // "null" all over the replay. Real text loses its leading and
        // trailing spaces for the same reason.
        $isIngestion = fn (Request $request) => $request->is('api/t/*')
            || $request->is('api/v1/*');

        $middleware->trimStrings(except: [$isIngestion]);
        $middleware->convertEmptyStringsToNull(except: [$isIngestion]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
