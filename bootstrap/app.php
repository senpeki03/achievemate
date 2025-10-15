<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureUserType;   // ✅ correct class name & import
// use App\Http\Middleware\RedirectIfAlreadyApplied; // <- only if you have it

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
    ->withMiddleware(function (Middleware $middleware) {
        // web stack
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // aliases
        $middleware->alias([
            'usertype' => EnsureUserType::class,      // ✅
            // 'already.applied' => RedirectIfAlreadyApplied::class, // only if it exists
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
