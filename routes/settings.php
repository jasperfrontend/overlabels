<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\BMACIntegrationController;
use App\Http\Controllers\Settings\BotAliasesController;
use App\Http\Controllers\Settings\BotCommandsController;
use App\Http\Controllers\Settings\BotSettingsController;
use App\Http\Controllers\Settings\CheckinIntegrationController;
use App\Http\Controllers\Settings\ControlUsageController;
use App\Http\Controllers\Settings\FourthwallIntegrationController;
use App\Http\Controllers\Settings\GpsIntegrationController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\KofiIntegrationController;
use App\Http\Controllers\Settings\StreamLabsIntegrationController;
use App\Http\Controllers\Settings\ThroneIntegrationController;
use App\Http\Controllers\Settings\UsageController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Old appearance URL kept as a permanent redirect so existing bookmarks
// and in-app deep links still land somewhere useful after the rename.
Route::redirect('/settings/appearance', '/settings/account', 301);
Route::redirect('/settings', '/settings/account', 301);

Route::middleware('auth.redirect')->group(function () {
    Route::get('/settings/account', function () {
        return inertia('settings/Account');
    })->name('settings.account');

    Route::delete('/settings/account', [AccountController::class, 'destroy'])
        ->name('settings.account.destroy');

    Route::get('/settings/usage', [UsageController::class, 'index'])
        ->name('settings.usage');

    Route::get('/settings/controls', [ControlUsageController::class, 'index'])
        ->name('settings.controls');

    Route::get('/settings/chat', function (Request $request) {
        return inertia('settings/Chat', [
            'chatFilters' => $request->user()->chatFilters(),
        ]);
    })->name('settings.chat');

    // Display filters for the chat overlay. These only decide what the overlay
    // draws - the overlay reads chat straight from Twitch, so nothing here
    // touches Twitch, the chatter, or anyone else's view of the channel.
    Route::patch('/settings/chat', function (Request $request) {
        $validated = $request->validate([
            'hide_commands' => 'required|boolean',
            'hidden_logins' => 'nullable|string|max:10000',
        ]);

        // Accepted as free text (one login per line) rather than a structured
        // list, because the UI is a textarea and a streamer pasting a messy
        // list should not get a validation error thrown back at them.
        $logins = collect(preg_split('/[\r\n,]+/', $validated['hidden_logins'] ?? ''))
            ->map(fn (string $login) => strtolower(trim(ltrim($login, '@'))))
            ->filter(fn (string $login) => $login !== '' && preg_match('/^[a-z0-9_]{1,25}$/', $login) === 1)
            ->unique()
            ->take(User::MAX_HIDDEN_LOGINS)
            ->values()
            ->all();

        $user = $request->user();
        $user->setPreference('chat_filters.hide_commands', $validated['hide_commands']);
        $user->setPreference('chat_filters.hidden_logins', $logins);
        $user->save();

        return back()->with('success', 'Chat display settings saved. Reload your overlay in OBS to apply them.');
    })->name('settings.chat.update');

    Route::patch('/settings/locale', function (Request $request) {
        $request->validate(['locale' => 'required|string|max:10']);
        $request->user()->setPreference('locale', $request->input('locale'))->save();

        return back();
    })->name('settings.locale');

    Route::patch('/settings/foreach-caps', function (Request $request) {
        $rules = [];
        foreach (array_keys(User::PREFERENCE_DEFAULTS['foreach_caps']) as $key) {
            $rules[$key] = 'required|integer|min:1|max:'.User::FOREACH_CAP_MAX;
        }
        $validated = $request->validate($rules);

        $user = $request->user();
        foreach ($validated as $key => $value) {
            $user->setPreference("foreach_caps.$key", (int) $value);
        }
        $user->save();

        return back();
    })->name('settings.foreach-caps');

    // Remember that a one-off NudgeBar has been clicked away. Not a settings
    // screen - it lives here because it is a preference write and this is where
    // those are. Dismissing twice is a no-op, so the client never has to check
    // first.
    Route::post('/nudges/{key}/dismiss', function (Request $request, string $key) {
        $user = $request->user();
        $dismissed = collect($user->preference('dismissed_nudges', []))
            ->push($key)
            ->unique()
            // Negative take keeps the NEWEST keys, so hitting the ceiling drops
            // an ancient dismissal rather than silently discarding the one the
            // user just made.
            ->take(-User::MAX_DISMISSED_NUDGES)
            ->values()
            ->all();

        $user->setPreference('dismissed_nudges', $dismissed)->save();

        return back();
    })->where('key', '[a-z0-9-]{1,64}')->name('nudges.dismiss');

    // External Integrations
    Route::prefix('settings/integrations')->name('settings.integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::get('/kofi', [KofiIntegrationController::class, 'show'])->name('kofi.show');
        Route::post('/kofi', [KofiIntegrationController::class, 'save'])->name('kofi.save');
        Route::patch('/kofi/test-mode', [KofiIntegrationController::class, 'setTestMode'])->name('kofi.test-mode');
        Route::post('/kofi/seed-count', [KofiIntegrationController::class, 'seedDonationCount'])->name('kofi.seed-count');
        Route::delete('/kofi', [KofiIntegrationController::class, 'disconnect'])->name('kofi.disconnect');

        Route::get('/overlabels-mobile', [GpsIntegrationController::class, 'show'])->name('overlabels-mobile.show');
        Route::post('/overlabels-mobile', [GpsIntegrationController::class, 'save'])->name('overlabels-mobile.save');
        Route::post('/overlabels-mobile/regenerate-token', [GpsIntegrationController::class, 'regenerateToken'])->name('overlabels-mobile.regenerate-token');
        Route::delete('/overlabels-mobile', [GpsIntegrationController::class, 'disconnect'])->name('overlabels-mobile.disconnect');
        Route::post('/overlabels-mobile/reset-session', [GpsIntegrationController::class, 'resetSession'])->name('overlabels-mobile.reset-session');
        Route::post('/overlabels-mobile/reset-lifetime', [GpsIntegrationController::class, 'resetLifetime'])->name('overlabels-mobile.reset-lifetime');

        Route::get('/checkin', [CheckinIntegrationController::class, 'show'])->name('checkin.show');
        Route::post('/checkin', [CheckinIntegrationController::class, 'save'])->name('checkin.save');
        Route::delete('/checkin', [CheckinIntegrationController::class, 'disconnect'])->name('checkin.disconnect');

        Route::get('/streamlabs', [StreamLabsIntegrationController::class, 'show'])->name('streamlabs.show');
        Route::get('/streamlabs/redirect', [StreamLabsIntegrationController::class, 'redirect'])->name('streamlabs.redirect');
        Route::patch('/streamlabs/test-mode', [StreamLabsIntegrationController::class, 'setTestMode'])->name('streamlabs.test-mode');
        Route::post('/streamlabs/seed-count', [StreamLabsIntegrationController::class, 'seedDonationCount'])->name('streamlabs.seed-count');
        Route::delete('/streamlabs', [StreamLabsIntegrationController::class, 'disconnect'])->name('streamlabs.disconnect');

        Route::get('/fourthwall', [FourthwallIntegrationController::class, 'show'])->name('fourthwall.show');
        Route::get('/fourthwall/redirect', [FourthwallIntegrationController::class, 'redirect'])->name('fourthwall.redirect');
        Route::patch('/fourthwall/test-mode', [FourthwallIntegrationController::class, 'setTestMode'])->name('fourthwall.test-mode');
        Route::post('/fourthwall/seed-count', [FourthwallIntegrationController::class, 'seedDonationCount'])->name('fourthwall.seed-count');
        Route::delete('/fourthwall', [FourthwallIntegrationController::class, 'disconnect'])->name('fourthwall.disconnect');

        Route::get('/bmac', [BMACIntegrationController::class, 'show'])->name('bmac.show');
        Route::post('/bmac', [BMACIntegrationController::class, 'save'])->name('bmac.save');
        Route::patch('/bmac/test-mode', [BMACIntegrationController::class, 'setTestMode'])->name('bmac.test-mode');
        Route::post('/bmac/seed-count', [BMACIntegrationController::class, 'seedDonationCount'])->name('bmac.seed-count');
        Route::delete('/bmac', [BMACIntegrationController::class, 'disconnect'])->name('bmac.disconnect');

        Route::get('/throne', [ThroneIntegrationController::class, 'show'])->name('throne.show');
        Route::post('/throne', [ThroneIntegrationController::class, 'connect'])->name('throne.connect');
        Route::patch('/throne/test-mode', [ThroneIntegrationController::class, 'setTestMode'])->name('throne.test-mode');
        Route::post('/throne/seed-count', [ThroneIntegrationController::class, 'seedDonationCount'])->name('throne.seed-count');
        Route::delete('/throne', [ThroneIntegrationController::class, 'disconnect'])->name('throne.disconnect');

        Route::patch('/bot', [BotSettingsController::class, 'setEnabled'])->name('bot.enabled');
    });

    // Bot Commands: user-authored chat commands templated against controls + Helix.
    Route::prefix('settings/bot/commands')->name('settings.bot.commands.')->group(function () {
        Route::get('/', [BotCommandsController::class, 'index'])->name('index');
        Route::get('/create', [BotCommandsController::class, 'create'])->name('create');
        Route::post('/', [BotCommandsController::class, 'store'])->name('store');
        Route::post('/preview', [BotCommandsController::class, 'preview'])->name('preview');
        Route::get('/{botCommand}/edit', [BotCommandsController::class, 'edit'])->name('edit');
        Route::patch('/{botCommand}', [BotCommandsController::class, 'update'])->name('update');
        Route::delete('/{botCommand}', [BotCommandsController::class, 'destroy'])->name('destroy');
    });

    // Bot Aliases: mod-only command rewrites that expand to another bot command before dispatch.
    Route::prefix('settings/bot/aliases')->name('settings.bot.aliases.')->group(function () {
        Route::get('/', [BotAliasesController::class, 'index'])->name('index');
        Route::get('/create', [BotAliasesController::class, 'create'])->name('create');
        Route::post('/', [BotAliasesController::class, 'store'])->name('store');
        Route::get('/{botAlias}/edit', [BotAliasesController::class, 'edit'])->name('edit');
        Route::patch('/{botAlias}', [BotAliasesController::class, 'update'])->name('update');
        Route::delete('/{botAlias}', [BotAliasesController::class, 'destroy'])->name('destroy');
    });
});
