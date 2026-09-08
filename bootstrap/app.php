<?php

use App\Http\Middleware\CheckBanned;
use App\Http\Middleware\CheckLockdown;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureValidTwitchToken;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RateLimitOverlayAccess;
use App\Http\Middleware\RedirectIfUnauthenticated;
use App\Http\Middleware\ValidateOverlayToken;
use App\Http\Middleware\VerifyBotListenerSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

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
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Lists are lists - preserve exactly what the user typed (empty
        // lines, trailing/leading whitespace, duplicates, all
        // intentional). Without these exemptions, the global TrimStrings
        // + ConvertEmptyStringsToNull middlewares would silently strip
        // the user's input before it reached the ListController. The
        // callback returns true to skip the conversion for this request.
        $middleware->trimStrings([
            fn ($request) => $request->is('dashboard/lists', 'dashboard/lists/*'),
        ]);
        $middleware->convertEmptyStringsToNull([
            fn ($request) => $request->is('dashboard/lists', 'dashboard/lists/*'),
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            HandleImpersonation::class,
            CheckBanned::class,
        ]);

        // Add Sanctum's stateful middleware to API routes
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            CheckBanned::class,
        ]);

        // Register middleware aliases for use in routes
        $middleware->alias([
            'auth.redirect' => RedirectIfUnauthenticated::class,
            'overlay.token' => ValidateOverlayToken::class,
            'rate.limit.overlay' => RateLimitOverlayAccess::class,
            'twitch.token' => EnsureValidTwitchToken::class,
            'admin.role' => EnsureAdminRole::class,
            'lockdown' => CheckLockdown::class,
            'check.banned' => CheckBanned::class,
            'bot.internal' => VerifyBotListenerSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A 419 means the CSRF/session token expired - usually a cleared cookie
        // or a session that died mid-visit. For an Inertia request, returning the
        // raw 419 leaves the SPA stuck on a response it can't parse. Convert it
        // into an Inertia full-page redirect to login (preserving where the user
        // was) so they re-authenticate instead of clicking into dead console
        // errors. Non-Inertia requests keep Laravel's default 419 page.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && $request->header('X-Inertia')) {
                return Inertia::location(route('login').'?redirect_to='.urlencode($request->fullUrl()));
            }

            return $response;
        });
    })->create();
