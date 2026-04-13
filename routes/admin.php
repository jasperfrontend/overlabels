<?php

use App\Http\Controllers\Admin\AdminAccessLogController;
use App\Http\Controllers\Admin\AdminAccessTokenController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminBanController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminKitController;
use App\Http\Controllers\Admin\AdminLockdownController;
use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTemplateTagController;
use App\Http\Controllers\Admin\AdminTwitchBotController;
use App\Http\Controllers\Admin\AdminTwitchEventController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['admin.role'])
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show')->withTrashed();
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role')->withTrashed();
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore')->withTrashed();
        Route::post('/users/{user}/kofi-seed', [AdminUserController::class, 'updateKofiSeed'])->name('users.kofi-seed')->withTrashed();

        // Kits
        Route::get('/kits', [AdminKitController::class, 'index'])->name('kits.index');
        Route::post('/kits/{kit}/set-starter', [AdminKitController::class, 'setStarter'])->name('kits.set-starter');

        // Templates
        Route::get('/templates', [AdminTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/{template}', [AdminTemplateController::class, 'show'])->name('templates.show');
        Route::patch('/templates/{template}', [AdminTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [AdminTemplateController::class, 'destroy'])->name('templates.destroy');

        // Events
        Route::get('/events', [AdminTwitchEventController::class, 'index'])->name('events.index');
        Route::delete('/events/prune', [AdminTwitchEventController::class, 'prune'])->name('events.prune');
        Route::get('/events/{event}', [AdminTwitchEventController::class, 'show'])->name('events.show');
        Route::patch('/events/{event}', [AdminTwitchEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [AdminTwitchEventController::class, 'destroy'])->name('events.destroy');
        Route::get('/external-events/{externalEvent}', [AdminTwitchEventController::class, 'showExternal'])->name('events.external.show');

        // Tags — categories must come before {tag} to avoid route conflict
        Route::get('/tags/categories', [AdminTemplateTagController::class, 'indexCategories'])->name('tags.categories.index');
        Route::patch('/tags/categories/{category}', [AdminTemplateTagController::class, 'updateCategory'])->name('tags.categories.update');
        Route::delete('/tags/categories/{category}', [AdminTemplateTagController::class, 'destroyCategory'])->name('tags.categories.destroy');
        Route::get('/tags', [AdminTemplateTagController::class, 'index'])->name('tags.index');
        Route::patch('/tags/{tag}', [AdminTemplateTagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [AdminTemplateTagController::class, 'destroy'])->name('tags.destroy');

        // Tokens
        Route::get('/tokens', [AdminAccessTokenController::class, 'index'])->name('tokens.index');
        Route::delete('/tokens/prune', [AdminAccessTokenController::class, 'prune'])->name('tokens.prune');
        Route::get('/tokens/{token}', [AdminAccessTokenController::class, 'show'])->name('tokens.show');
        Route::delete('/tokens/{token}', [AdminAccessTokenController::class, 'destroy'])->name('tokens.destroy');

        // Sessions
        Route::get('/sessions', [AdminSessionController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/ip-lookup/{ip}', [AdminSessionController::class, 'ipLookup'])->name('sessions.ip-lookup')->where('ip', '[0-9a-fA-F.:]+');
        Route::delete('/sessions/{session}', [AdminSessionController::class, 'destroy'])->name('sessions.destroy');

        // Bans
        Route::get('/bans', [AdminBanController::class, 'index'])->name('bans.index');
        Route::post('/bans', [AdminBanController::class, 'store'])->name('bans.store');
        Route::delete('/bans/{ban}', [AdminBanController::class, 'destroy'])->name('bans.destroy');
        Route::post('/bans/from-session', [AdminBanController::class, 'banFromSession'])->name('bans.from-session');

        // Logs
        Route::delete('/logs/prune', [AdminAccessLogController::class, 'prune'])->name('logs.prune');
        Route::get('/logs', [AdminAccessLogController::class, 'index'])->name('logs.index');
        Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit.index');

        // Impersonation — stop must be before {user} to avoid "stop" being treated as a user ID
        Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
        Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');

        // Lockdown
        Route::get('/lockdown', [AdminLockdownController::class, 'index'])->name('lockdown.index');
        Route::post('/lockdown/activate', [AdminLockdownController::class, 'activate'])->name('lockdown.activate');
        Route::post('/lockdown/deactivate', [AdminLockdownController::class, 'deactivate'])->name('lockdown.deactivate');

        // Onboarding preview
        Route::post('/onboarding-preview', [AdminDashboardController::class, 'previewOnboarding'])->name('onboarding.preview');

        // Twitch Bot (@overlabels shared account)
        Route::get('/twitchbot', [AdminTwitchBotController::class, 'index'])->name('twitchbot.index');
    });

// Twitch Bot OAuth flow - must live at /auth/twitchbot/callback to match the Twitch app's
// registered redirect URI. Admin-only. Not under the /admin/ prefix.
Route::middleware(['admin.role'])->group(function () {
    Route::get('/auth/twitchbot', [AdminTwitchBotController::class, 'redirect'])->name('admin.twitchbot.redirect');
    Route::get('/auth/twitchbot/callback', [AdminTwitchBotController::class, 'callback'])->name('admin.twitchbot.callback');
});
