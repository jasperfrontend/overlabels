<?php

use App\Console\Commands\GamejamDebug;
use App\Events\GameStateChanged;
use App\Events\UserRegistered;
use App\Http\Controllers\AlertMuteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventDeletionController;
use App\Http\Controllers\EventTemplateMappingController;
use App\Http\Controllers\ExternalEventController;
use App\Http\Controllers\FreesoundController;
use App\Http\Controllers\GamejamAdminController;
use App\Http\Controllers\GpsSessionController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HelpReferenceController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\IntegrationSuggestionController;
use App\Http\Controllers\KitController;
use App\Http\Controllers\ListActionWebController;
use App\Http\Controllers\ListAppenderController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\OverlayAccessTokenController;
use App\Http\Controllers\OverlayControlController;
use App\Http\Controllers\OverlayReportController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RecipeInstanceController;
use App\Http\Controllers\RoomBuilderController;
use App\Http\Controllers\Settings\FourthwallIntegrationController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\StreamLabsIntegrationController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StreamSessionController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\TestingController;
use App\Http\Controllers\TwitchDataController;
use App\Http\Controllers\TwitchEventController;
use App\Http\Controllers\TwitchEventSubController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WhatsNewController;
use App\Http\Controllers\WiringController;
use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\Game;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchScopeService;
use App\Services\TwitchTokenService;
use App\Support\HelpPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// gamejam routes
Route::get('/gamejam', function () {
    return Inertia::render('gamejam/index');
})->name('gamejam');

Route::middleware(['auth.redirect'])->prefix('gamejam/admin')->name('gamejam.admin.')->group(function () {
    Route::get('/', [GamejamAdminController::class, 'index'])->name('index');
    Route::post('/start', [GamejamAdminController::class, 'start'])->name('start');
    Route::post('/end', [GamejamAdminController::class, 'end'])->name('end');
    Route::post('/debug/toggle', [GamejamAdminController::class, 'toggleDebug'])->name('debug.toggle');
});

// eventually place the route here that renders all active rooms in the gamejam

Route::get('/gamejam/live/{login}', function (string $login) {
    $login = strtolower($login);

    $user = User::where('bot_enabled', true)
        ->whereNotNull('twitch_data')
        ->get()
        ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);

    abort_unless($user, 404);

    $game = Game::activeFor($user);

    return Inertia::render('gamejam/live', [
        'broadcasterId' => (string) $user->twitch_id,
        'broadcasterLogin' => $login,
        'snapshot' => $game ? GameStateChanged::snapshotFor($game) : null,
        'debugEnabled' => GamejamDebug::isEnabledFor($user),
    ]);
})->where('login', '[a-z0-9_]+')->name('gamejam.live');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('/kaylin', fn () => response('Kaylin is the voice of Overlabels.', 200, ['Content-Type' => 'text/plain']));

// Help pages are markdown files in resources/help/pages. Both the URL and the
// route name are derived from the slug, so adding a page is adding a file - no
// route, no controller, no Vue component. A slug ending in `index` maps to its
// parent path, so index.md is /help and bot/index.md is /help/bot. Registration
// reads the filesystem once at boot and is captured by route caching.
foreach (HelpPage::all() as $helpSlug) {
    $helpPath = trim((string) preg_replace('#(^|/)index$#', '', $helpSlug), '/');

    $helpUrl = HelpPage::url($helpSlug);
    $helpName = 'help'.($helpPath === '' ? '' : '.'.str_replace('/', '.', $helpPath));

    Route::get($helpUrl, [HelpController::class, 'show'])
        ->defaults('slug', $helpSlug)
        ->name($helpName);

    // Same page as plain markdown for machines (llms.txt points here).
    Route::get($helpUrl.'.md', [HelpController::class, 'markdown'])
        ->defaults('slug', $helpSlug)
        ->name($helpName.'.md');
}

// Interactive help pages that are NOT prose and stay as Vue components:
// integration presets renders live data from controlPresets.ts with a fuzzy
// search, so freezing it into markdown would drift from its source.
Route::get('/help/integration-presets', function () {
    return Inertia::render('help/IntegrationPresets');
})->name('help.integration-presets');

// This page was /help/bot/commands until Jul 2026, spent a month at
// /help/bot/expressions, and is back. Both old URLs stay pointed at it - the
// .md variant included, because llms.txt named it and crawlers have it.
Route::redirect('/help/bot/expressions', '/help/bot/commands', 301);
Route::redirect('/help/bot/expressions.md', '/help/bot/commands.md', 301);

// The manifesto was a top-level route before the help pages became markdown.
// Google still has the old URL and reported it as a 404.
Route::redirect('/manifesto', '/help/manifesto', 301);

Route::get('/help/gamejam', function () {
    return Inertia::render('help/gamejam/Index');
})->name('help.gamejam');

// The hand-written per-service control pages were replaced by the generated
// `integration-controls` category. They were filed under eventsub-tags despite
// documenting controls, and they had gone stale - each claimed the shared
// schema covered "all four integrations" when it is seven. These must be
// declared BEFORE the catch-all reference route, which would otherwise match
// them and 404 on the missing markdown. Reference pages are the best-indexed
// part of the site, so they get 301s rather than dead URLs.
// Keyed by old slug because it does not always match the service key: Ko-fi's
// page was filed as `ko-fi-...` while the registry key is `kofi`, which is the
// reason that entry survived a search for "kofi" in the first place.
foreach ([
    'ko-fi-auto-provisioned-controls' => 'kofi',
    'streamlabs-auto-provisioned-controls' => 'streamlabs',
    'fourthwall-auto-provisioned-controls' => 'fourthwall',
] as $legacySlug => $service) {
    Route::redirect(
        "/help/reference/eventsub-tags/{$legacySlug}",
        "/help/reference/integration-controls/{$service}",
        301,
    );
}

// The three "for machines" explainers (llms.txt, the .md convention, the JSON
// index) were filed as reference entries for a year. They are prose, so they
// were the only reference pages anyone would want as markdown - and the one
// reference URL WITHOUT a .md twin was the page explaining the .md twin. They
// are guides now, which gives them the twin for free and leaves the reference
// JSON-first. Same reasoning as above for the 301s: these are indexed.
foreach (['llms-txt', 'markdown-endpoints', 'help-reference-index-json'] as $machineSlug) {
    Route::redirect("/help/reference/for-machines/{$machineSlug}", "/help/{$machineSlug}", 301);
}

// Every reference entry has a .md twin, same convention as the prose pages.
// Declared BEFORE the catch-all: its slug pattern admits a dot, so
// /help/reference/x/y.md would otherwise reach show() and 404 on a slug that
// literally ends in ".md". The index itself (/help/reference) has no twin -
// there is no file behind it, and the whole reference is one JSON fetch.
Route::get('/help/reference/{category}/{slug}.md', [HelpReferenceController::class, 'markdown'])
    ->where(['category' => '[a-z0-9\-]+', 'slug' => '[a-zA-Z0-9_\-\.]+?'])
    ->name('help.reference.md');

Route::get('/help/reference/{category?}/{slug?}', [HelpReferenceController::class, 'show'])
    ->where(['category' => '[a-z0-9\-]+', 'slug' => '[a-zA-Z0-9_\-\.]+'])
    ->name('help.reference');

// Updates (blog-style platform announcements). Public: these are announcement
// posts we want linkable from anywhere and indexable, so a login wall would
// defeat the point. The controller only ever queries the published() scope
// (published_at <= now), so future-dated posts stay invisible to guests.
Route::prefix('updates')->name('updates.')->group(function () {
    Route::get('/', [UpdateController::class, 'index'])->name('index');
    Route::get('/{slug}', [UpdateController::class, 'show'])->name('show')->where('slug', '[a-z0-9-]+');
});

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Dev-only tile-map builder. Guarded by admin.role + env=local check in the controller.
Route::middleware(['admin.role'])->prefix('dev/room-builder')->name('dev.room-builder.')->group(function () {
    Route::get('/{room}', [RoomBuilderController::class, 'show'])->where('room', '[0-9]+')->name('show');
    Route::get('/{room}/assets', [RoomBuilderController::class, 'assets'])->where('room', '[0-9]+')->name('assets');
    Route::post('/{room}', [RoomBuilderController::class, 'save'])->where('room', '[0-9]+')->name('save');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.index');

Route::get('/dashboard/recipes', [RecipeInstanceController::class, 'index'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.recipes');

Route::post('/recipes/instances/{instance}/fire-button', [RecipeInstanceController::class, 'fireButton'])
    ->middleware(['auth.redirect'])
    ->name('recipes.instances.fire-button');

Route::get('/dashboard/recents', [DashboardController::class, 'recentActivity'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.recents');

Route::get('/dashboard/gps-sessions', [GpsSessionController::class, 'index'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.gps-sessions');

Route::delete('/dashboard/gps-sessions/{sessionId}', [GpsSessionController::class, 'destroy'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.gps-sessions.destroy');

Route::get('/dashboard/stream-sessions', [StreamSessionController::class, 'index'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.stream-sessions');

Route::get('/dashboard/events', [DashboardController::class, 'recentEvents'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.events');

Route::post('/dashboard/events/mute', [AlertMuteController::class, 'update'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.events.mute');

// What's New card. Both writes touch update_dismissals for the current user
// only and answer with back(), so the dashboard re-renders from the same
// selection query that drew the card in the first place.
Route::post('/dashboard/whats-new/seen', [WhatsNewController::class, 'markSeen'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.whats-new.seen');

Route::delete('/dashboard/whats-new/seen', [WhatsNewController::class, 'undo'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.whats-new.undo');

Route::delete('/dashboard/whats-new/{update}', [WhatsNewController::class, 'dismiss'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.whats-new.dismiss');

// Only used by a CTA that leaves the app - an internal one is observed by
// MarkWhatsNewVisited on the way in, with no help from the browser.
Route::post('/dashboard/whats-new/{update}/visited', [WhatsNewController::class, 'markVisited'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.whats-new.visited');

// Token-authed events feed shell (phone-friendly /dashboard/events sibling).
// Served without auth on purpose: the overlay token lives in the URL fragment
// and is read client-side, so the server never sees it here. The shell shows
// nothing until the Vue app authenticates against /api/events with the token.
Route::get('/events/feed', function () {
    return view('events.feed');
})->name('events.feed');

Route::get('/login', [PageController::class, 'notAuthorized'])
    ->middleware(['guest'])
    ->name('login');

Route::get('/twitchdata', [TwitchDataController::class, 'index'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata');

Route::get('/twitchdata/refresh/expensive', [TwitchDataController::class, 'getLiveTwitchData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.expensive');

Route::post('/twitchdata/refresh/all', [TwitchDataController::class, 'refreshAllTwitchApiData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.all');

Route::post('/twitchdata/refresh/user', [TwitchDataController::class, 'refreshUserInfoData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.user');

Route::post('/twitchdata/refresh/info', [TwitchDataController::class, 'refreshChannelInfoData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.info');

Route::post('/twitchdata/refresh/following', [TwitchDataController::class, 'refreshFollowedChannelsData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.following');

Route::post('/twitchdata/refresh/followers', [TwitchDataController::class, 'refreshChannelFollowersData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.followers');

Route::post('/twitchdata/refresh/subscribers', [TwitchDataController::class, 'refreshSubscribersData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.subscribers');

Route::post('/twitchdata/refresh/goals', [TwitchDataController::class, 'refreshGoalsData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.goals');

Route::get('/overlay/{slug}', [OverlayTemplateController::class, 'serveAuthenticated'])
    ->name('overlay.authenticated')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

Route::get('/overlay/{slug}/public', [OverlayTemplateController::class, 'servePublic'])
    ->name('overlay.public')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

// A public kit as plain markdown: the kit, then every overlay in it described
// exactly as its own `.md` describes it.
//
// This sits OUTSIDE the `auth.redirect` group that every other kit route lives
// in, and that asymmetry is the point. `kits.show` needs a login; a URL you
// hand to a language model cannot. It opens no new surface: a private template
// inside a public kit is listed but its source is withheld, so every byte of
// source here is already readable at that template's own public `.md`.
Route::get('/kits/{kit}.md', [KitController::class, 'markdown'])
    ->name('kits.markdown')
    ->where('kit', '[0-9]+');

// The public overlay as plain markdown, so one URL is enough for a language
// model to understand a whole overlay: source, controls, required integrations
// and alert wiring. Same `.md` convention as the help pages above.
//
// Kept under `overlay.*` (unlike reports.store) because nothing in the frontend
// resolves it - the preview page is handed the URL as a prop, so Ziggy's
// blanket `!overlay.*` deny never comes into it.
Route::get('/overlay/{slug}/public.md', [OverlayTemplateController::class, 'servePublicMarkdown'])
    ->name('overlay.public.markdown')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

Route::get('/overlay/{slug}/public/screenshot', [OverlayTemplateController::class, 'servePublicScreenshot'])
    ->name('overlay.public.screenshot')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

// Reporting a public overlay. Open to logged-out visitors on purpose - most
// people who see a public overlay arrived from a shared link and have no
// account. Spam defence is the throttle plus the honeypot/timing checks in
// the controller.
//
// Named `reports.store` rather than `overlay.report` even though it lives
// under /overlay: config/ziggy.php hides `!overlay.*` from every frontend
// payload, and Ziggy's filter lets a negation veto an explicit include, so a
// route under that name cannot be exposed to the client without punching a
// hole in the blanket deny. This is the one overlay route the frontend has to
// be able to call, so it sits outside that namespace instead. It pairs with
// the admin.reports.* routes that read the same table.
Route::post('/overlay/{slug}/report', [OverlayReportController::class, 'store'])
    ->middleware('throttle:overlay-report')
    ->name('reports.store')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

// Initiate login with Twitch
Route::get('/auth/redirect/twitch', function (Request $request) {
    // Preserve the intended URL during OAuth flow
    if ($request->session()->has('url.intended')) {
        // Session will persist through OAuth flow
    }

    /** @var AbstractProvider $driver */
    $driver = Socialite::driver('twitch');

    // ?reauth=1 forces Twitch to show the consent screen again even when the
    // user has previously authorized - required for picking up new scopes
    // that weren't in the original grant (hype train, polls, etc.).
    if ($request->query('reauth') === '1') {
        $driver->with(['force_verify' => 'true']);
    }

    return $driver->scopes(TwitchScopeService::REQUIRED_SCOPES)->redirect();
});

// Refresh Twitch token endpoint
Route::post('/auth/refresh/twitch', function () {
    $user = Auth::user();

    if (! $user) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }

    $tokenService = app(TwitchTokenService::class);

    if ($tokenService->refreshUserToken($user)) {
        return response()->json(['success' => true, 'message' => 'Token refreshed successfully']);
    }

    return response()->json(['error' => 'Failed to refresh token', 'requires_reauth' => true], 401);
})->middleware('auth')->name('auth.refresh.twitch');

Route::get('/auth/callback/twitch', function (TwitchScopeService $scopeService) {
    try {
        $twitchUser = Socialite::driver('twitch')->user();

        $twitchService = new TwitchApiService;
        $extendedData = $twitchService->getExtendedUserData(
            $twitchUser->token,
            $twitchUser->getId()
        );

        // Always match by Twitch ID only (including soft-deleted users)
        $user = User::withTrashed()->where('twitch_id', $twitchUser->getId())->first();
        $isNewUser = ! $user;

        // Restore soft-deleted users on re-login
        if ($user && $user->trashed()) {
            $user->restore();
        }

        // Socialite returns approvedScopes from the token response - use the
        // shared sanitizer to normalize (Twitch mixes array and space-string).
        $approvedScopes = TwitchScopeService::sanitizeScopeList($twitchUser->approvedScopes ?? []);

        // Remember pre-reauth missing-scope state so we can auto-dispatch
        // resubscribe if this login unlocks new event types for the user.
        $priorMissingScopes = $user ? $scopeService->getMissingScopes($user) : TwitchScopeService::REQUIRED_SCOPES;

        // Twitch returns the user's email in the OAuth payload. We never want
        // to store it - not as a column, not inside twitch_data JSON. Strip
        // before persisting.
        $sanitizedTwitchPayload = array_diff_key(
            array_merge($twitchUser->user, $extendedData),
            array_flip(['email', 'email_verified', 'verified'])
        );

        if (! $user) {
            // Create a new user if not found
            $user = User::create([
                'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'twitch_id' => $twitchUser->getId(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                'twitch_data' => $sanitizedTwitchPayload,
                'twitch_scopes' => $approvedScopes ?: null,
                'webhook_secret' => bin2hex(random_bytes(32)),
                'eventsub_auto_connect' => true, // New users default to auto-connect
            ]);

            // Dispatch event for new user registration
            UserRegistered::dispatch($user);
        } else {
            // Existing user — update tokens and data
            $updateData = [
                'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                'twitch_data' => $sanitizedTwitchPayload,
            ];

            // Only overwrite twitch_scopes when Socialite actually gave us a list -
            // empty array on the initial response is ambiguous and we'd rather
            // keep the prior stored set than blank it out.
            if (! empty($approvedScopes)) {
                $updateData['twitch_scopes'] = $approvedScopes;
            }

            // Backfill webhook_secret for users created before per-user secrets
            if (! $user->webhook_secret) {
                $updateData['webhook_secret'] = bin2hex(random_bytes(32));
            }

            $user->update($updateData);
        }

        // Remember, always. Sessions expire after SESSION_LIFETIME (120 minutes
        // of idle by default, and prod does not override it), so without a
        // remember cookie two hours away from the site means re-authenticating
        // through Twitch - which in practice meant logging in every day. The
        // remember cookie is cycled on logout, and Auth::logout() below clears
        // it for a banned user before the session is thrown away.
        Auth::login($user, true);

        // Block banned users from logging in. 404 rather than a redirect to an
        // explanation page: a banned requester gets nothing anywhere else, and
        // the OAuth callback is not the place to make an exception.
        if ($user->isBanned()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            abort(404);
        }

        $currentMissingScopes = $scopeService->getMissingScopes($user);
        $scopesJustUnlocked = ! empty(array_diff($priorMissingScopes, $currentMissingScopes));

        // Auto-setup EventSub subscriptions for new users, users who have
        // auto-connect enabled but aren't connected yet, or users whose
        // reauth just unlocked new scopes (setupUserSubscriptions is
        // idempotent - it skips already-enabled subscriptions).
        $shouldSetup = $isNewUser
            || ($user->eventsub_auto_connect && ! $user->eventsub_connected_at)
            || ($scopesJustUnlocked && $user->eventsub_auto_connect);

        if ($shouldSetup) {
            try {
                Log::info('Dispatching EventSub setup for user', [
                    'user_id' => $user->id,
                    'twitch_id' => $user->twitch_id,
                    'is_new_user' => $isNewUser,
                    'scopes_just_unlocked' => $scopesJustUnlocked,
                ]);

                // Dispatch the job to setup EventSub subscriptions
                SetupUserEventSubSubscriptions::dispatch($user);

            } catch (Exception $e) {
                Log::warning('Failed to dispatch EventSub setup job', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail authentication if EventSub setup fails
            }
        }

        // Redirect to the intended URL if it's a safe full-page destination.
        // JSON-returning endpoints (api/, etc.) must never be used here - they
        // get stored as url.intended when fetch() follows a 302 redirect to
        // /login, which would land the user on a raw JSON response.
        $intended = session()->pull('url.intended');
        if ($intended) {
            $path = parse_url($intended, PHP_URL_PATH) ?? '';
            $jsonPaths = ['/api/'];
            $isSafe = ! collect($jsonPaths)->contains(fn ($prefix) => str_starts_with($path, $prefix));
            if ($isSafe) {
                return redirect($intended);
            }
        }

        return redirect('/dashboard');

    } catch (HttpException $e) {
        // A deliberate abort() is not an OAuth failure. Without this the
        // catch-all below swallowed the banned-user 404 - HttpException is an
        // Exception - and handed a banned account the friendly "Authentication
        // failed, please try again" redirect instead of the hard 404 that every
        // other route gives them.
        throw $e;
    } catch (Exception $e) {
        Log::error('Twitch OAuth callback failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect('/')->with('error', 'Authentication failed. Please try again.');
    }
});

// StreamLabs OAuth callback
Route::get('/auth/callback/streamlabs', [StreamLabsIntegrationController::class, 'callback'])
    ->middleware('auth.redirect')
    ->name('auth.callback.streamlabs');

// Fourthwall OAuth callback (path matches FW_REDIRECT_URL registered with the app)
Route::get('/auth/redirect/fw', [FourthwallIntegrationController::class, 'callback'])
    ->middleware('auth.redirect')
    ->name('auth.callback.fourthwall');

// Logout lives in routes/auth.php (named 'logout'). A duplicate POST /logout
// closure used to sit here and never ran: RouteCollection keys on method+URI,
// auth.php is required further down this file, and the later registration
// wins. Editing it had no effect on anything.

Route::middleware('auth.redirect')->group(function () {

    // Wiring - what is wired up and what is one step short of working.
    // Born /skills; old bookmarks keep working.
    Route::redirect('/skills', '/wiring', 301);
    Route::redirect('/wiring', '/settings/wiring', 301);
    Route::get('/settings/wiring', [WiringController::class, 'index'])->name('wiring.index');

    // Testing Guide
    Route::get('/testing', [TestingController::class, 'index'])->name('testing.index');

    // Access Token Management
    Route::prefix('tokens')->name('tokens.')->group(function () {
        Route::get('/', [OverlayAccessTokenController::class, 'index'])->name('index');
        Route::post('/', [OverlayAccessTokenController::class, 'store'])->name('store');
        Route::post('/{token}/revoke', [OverlayAccessTokenController::class, 'revoke'])->name('revoke');
        Route::delete('/{token}', [OverlayAccessTokenController::class, 'destroy'])->name('destroy');
    });

    // Lists (user-managed OptionSets, surfaced as a top-level dashboard
    // section). User-authored rows live alongside recipe-installed lists.
    Route::prefix('dashboard/lists')->name('lists.')->group(function () {
        Route::get('/', [ListController::class, 'index'])->name('index');
        Route::post('/', [ListController::class, 'store'])->name('store');

        // !list meta-command config (one per user). Routes registered
        // BEFORE the {list} parameterised routes so 'meta-command'
        // doesn't get bound to the route-model resolver.
        Route::get('/meta-command', [ListActionWebController::class, 'getMeta'])->name('meta-command.get');
        Route::put('/meta-command', [ListActionWebController::class, 'saveMeta'])->name('meta-command.save');

        // Single-list detail page. Registered after the literal routes above so
        // the {slug} segment can't swallow 'meta-command'. Resolved scoped to
        // the user inside the controller (slugs are per-user, not global).
        Route::get('/{slug}', [ListController::class, 'show'])->name('show');

        Route::put('/{list}', [ListController::class, 'update'])->name('update');
        Route::delete('/{list}', [ListController::class, 'destroy'])->name('destroy');

        // List Append commands - chat commands that append to a list.
        // Returns JSON; consumed by the Lists Vue page inline rather
        // than Inertia full-page reloads.
        Route::prefix('{list}/appenders')->name('appenders.')->group(function () {
            Route::get('/', [ListAppenderController::class, 'index'])->name('index');
            Route::post('/', [ListAppenderController::class, 'store'])->name('store');
            Route::put('/{appender}', [ListAppenderController::class, 'update'])->name('update');
            Route::delete('/{appender}', [ListAppenderController::class, 'destroy'])->name('destroy');
        });

        // List Actions (dashboard buttons + the !list meta-command's
        // dashboard equivalent). Same vocabulary as the chat command.
        Route::post('/{list}/actions', [ListActionWebController::class, 'runAction'])->name('actions.run');

        // Recent-events feed config. Turns a list into a StreamElements-style
        // recent-events widget fed from the recents page.
        Route::put('/{list}/event-feed', [ListController::class, 'updateEventFeed'])->name('event-feed');

        // Snapshots
        Route::prefix('{list}/snapshots')->name('snapshots.')->group(function () {
            Route::get('/', [ListActionWebController::class, 'listSnapshots'])->name('index');
            Route::post('/manual', [ListActionWebController::class, 'manualSnapshot'])->name('manual');
            Route::post('/{snapshot}/restore', [ListActionWebController::class, 'restoreSnapshot'])->name('restore');
            Route::patch('/{snapshot}/pin', [ListActionWebController::class, 'togglePin'])->name('pin');
            Route::delete('/{snapshot}', [ListActionWebController::class, 'deleteSnapshot'])->name('destroy');
        });
    });

    // Builder - compose an overlay from blocks on a CSS grid
    Route::get('/builder', [OverlayTemplateController::class, 'builder'])->name('builder.create');

    // Template Management - Full resource routes
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [OverlayTemplateController::class, 'index'])->name('index');
        Route::get('/create', [OverlayTemplateController::class, 'create'])->name('create');
        Route::post('/', [OverlayTemplateController::class, 'store'])
            ->middleware('throttle:template-write')->name('store');
        Route::post('/import', [OverlayTemplateController::class, 'import'])
            ->middleware('throttle:template-write')->name('import');
        // Block routes must precede the {template} wildcard.
        Route::get('/blocks/library', [OverlayTemplateController::class, 'blockLibrary'])->name('blocks.library');
        Route::get('/blocks/{template}/snapshot', [OverlayTemplateController::class, 'blockSnapshot'])->name('blocks.snapshot');
        Route::get('/{template}', [OverlayTemplateController::class, 'show'])->name('show');
        Route::get('/{template}/edit', [OverlayTemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [OverlayTemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [OverlayTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{template}/fork', [OverlayTemplateController::class, 'fork'])
            ->middleware('throttle:template-write')->name('fork');
        Route::put('/{template}/target-overlays', [OverlayTemplateController::class, 'updateTargetOverlays'])->name('target-overlays');
        Route::put('/{template}/triggers', [OverlayTemplateController::class, 'updateTriggers'])->name('triggers');
        Route::put('/{template}/screenshot', [OverlayTemplateController::class, 'updateScreenshot'])->name('screenshot');
    });

    // Image uploads - all image uploads route through here so we can
    // rate-limit, validate dimensions, and track for orphan cleanup. The
    // frontend never talks to the storage provider directly.
    Route::post('/images/upload', [ImageUploadController::class, 'upload'])
        ->middleware('throttle:image-upload')
        ->name('images.upload');

    // Integration Suggestions (rate limited: 3 per hour per user)
    Route::post('/integration-suggestions', [IntegrationSuggestionController::class, 'store'])
        ->middleware('throttle:3,60')
        ->name('integration-suggestions.store');

    // Controls Management
    Route::prefix('templates/{template}/controls')
        ->name('controls.')
        ->group(function () {
            Route::get('/', [OverlayControlController::class, 'index'])->name('index');
            Route::post('/', [OverlayControlController::class, 'store'])->name('store');
            Route::post('/import', [OverlayControlController::class, 'importForkedControls'])->name('import');
            Route::put('/{control}', [OverlayControlController::class, 'update'])->name('update');
            Route::delete('/{control}', [OverlayControlController::class, 'destroy'])->name('destroy');
            Route::post('/{control}/value', [OverlayControlController::class, 'setValue'])->name('value');
        });

    // Freesound search + per-user sound library. Search proxies the
    // Freesound v2 API (license-filtered to CC0 + Attribution server-side);
    // save/destroy manage user_freesound_sounds rows. No audio bytes touch
    // our servers - we hotlink Freesound's preview-hq-mp3 URLs.
    Route::prefix('freesound')->name('freesound.')->group(function () {
        Route::get('/search', [FreesoundController::class, 'search'])->name('search');
        Route::post('/library', [FreesoundController::class, 'save'])->name('save');
        Route::delete('/library/{sound}', [FreesoundController::class, 'destroy'])->name('destroy');
    });

    // Kit Management
    Route::prefix('kits')->name('kits.')->group(function () {
        Route::get('/', [KitController::class, 'index'])->name('index');
        Route::get('/create', [KitController::class, 'create'])->name('create');
        Route::post('/', [KitController::class, 'store'])->name('store');
        Route::get('/{kit}', [KitController::class, 'show'])->name('show');
        Route::get('/{kit}/edit', [KitController::class, 'edit'])->name('edit');
        Route::put('/{kit}', [KitController::class, 'update'])->name('update');
        Route::delete('/{kit}', [KitController::class, 'destroy'])->name('destroy');
        Route::post('/{kit}/fork', [KitController::class, 'fork'])
            ->middleware('throttle:kit-fork')->name('fork');
    });

    // Trigger overview - read-only matrix; per-template editing lives on
    // the template edit page (Triggers tab).
    Route::redirect('/triggers', '/settings/triggers', 301);
    Route::get('/settings/triggers', [EventTemplateMappingController::class, 'index'])->name('triggers.index');

    // Old URL, kept so existing bookmarks and open tabs don't 404. Unnamed on
    // purpose: nothing should link here, and Ziggy skips unnamed routes.
    Route::redirect('/alerts', '/triggers', 301);

    // EventSub connect - called from settings/integrations/index.vue
    Route::post('/eventsub/connect', [IntegrationController::class, 'connectEventSub'])
        ->name('eventsub.connect');

    // Template tag reference, showing the account's current values
    Route::get('/tags', [TemplateTagController::class, 'index'])
        ->name('tags.generator');

    // Replay a historical event as an alert
    Route::post('/events/{twitchEvent}/replay', [TwitchEventSubController::class, 'replay'])->name('events.replay');

    // Fire a synthetic channel.cheer for alert/control testing
    Route::post('/twitch/test-cheer', [TwitchEventSubController::class, 'testCheer'])->name('twitch.test-cheer');

    // Replay a stored external (Ko-fi, etc.) event as an alert
    Route::post('/external-events/{externalEvent}/replay', [ExternalEventController::class, 'replay'])->name('external-events.replay');

    // Bulk-remove rows from the recent-events feed (test events, bot spam).
    // Spans both event tables, so it takes {source, id} pairs rather than ids.
    Route::post('/events/bulk-delete', [EventDeletionController::class, 'destroy'])->name('events.bulk-delete');

    // Twitch events API - protected by authentication
    Route::prefix('/api/twitch/events')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [TwitchEventController::class, 'index']);
        Route::get('/{id}', [TwitchEventController::class, 'show']);
        Route::put('/{id}/process', [TwitchEventController::class, 'markAsProcessed']);
        Route::post('/batch-process', [TwitchEventController::class, 'batchMarkAsProcessed']);
        Route::delete('/{id}', [TwitchEventController::class, 'destroy']);
    });
});

// Public map pages (no auth, opt-in via map_sharing_enabled). The slug is a
// Sqids-encoded Twitch ID so the numeric ID never appears in the URL.
Route::get('/map/{slug}', [MapController::class, 'live'])->name('map.live');
Route::get('/map/{slug}/{sessionId}', [MapController::class, 'session'])->name('map.session');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

// Final 404 handler. Must be Route::fallback (not Route::any with a
// wildcard) because the latter wins by registration order over routes
// registered later in the boot lifecycle - notably /broadcasting/auth
// (auto-registered by the BroadcastServiceProvider) - and would return
// an HTML 404 page in response to Echo's channel-auth POST requests.
// Route::fallback() always matches last regardless of order.
Route::fallback([PageController::class, 'notfound']);
