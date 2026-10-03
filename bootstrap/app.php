<?php

use App\Http\Middleware\CheckSessionVersion;
use App\Http\Middleware\EnforceAdminMfa;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordAdminWrites;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Group-wide rather than per-route: an admin route added later
            // cannot forget to audit itself. Maveren's H-1 was a logging
            // function every call site had to remember, and none did.
            RecordAdminWrites::class,
            // Same reasoning, and it must run after the audit recorder so the
            // redirect it issues is itself recorded.
            EnforceAdminMfa::class,
            // Ends sessions issued under a superseded password. Group-wide so
            // that no authenticated surface can be reached by a revoked
            // session, whichever route it asks for.
            CheckSessionVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
