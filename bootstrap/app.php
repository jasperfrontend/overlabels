<?php

use App\Http\Middleware\EnsureValidTwitchToken;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RateLimitOverlayAccess;
use App\Http\Middleware\ValidateOverlayToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware('web')->group(base_path('routes/settings.php'));
            Route::middleware('web')->group(base_path('routes/storage.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Add Sanctum's stateful middleware to API routes
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Register middleware aliases for use in routes
        $middleware->alias([
            'overlay.token' => ValidateOverlayToken::class,
            'rate.limit.overlay' => RateLimitOverlayAccess::class,
            'twitch.token' => EnsureValidTwitchToken::class,
            'storage.refresh' => \App\Http\Middleware\HandleStorageTokenRefresh::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
