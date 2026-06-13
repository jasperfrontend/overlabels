<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\BMACIntegrationController;
use App\Http\Controllers\Settings\BotAliasesController;
use App\Http\Controllers\Settings\BotExpressionsController;
use App\Http\Controllers\Settings\BotSettingsController;
use App\Http\Controllers\Settings\FourthwallIntegrationController;
use App\Http\Controllers\Settings\GpsIntegrationController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\KofiIntegrationController;
use App\Http\Controllers\Settings\StreamElementsIntegrationController;
use App\Http\Controllers\Settings\StreamLabsIntegrationController;
use App\Http\Controllers\Settings\UsageController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Old appearance URL kept as a permanent redirect so existing bookmarks
// and in-app deep links still land somewhere useful after the rename.
Route::redirect('/settings/appearance', '/settings/account', 301);

Route::middleware('auth.redirect')->group(function () {
    Route::get('/settings/account', function () {
        return inertia('settings/Account');
    })->name('settings.account');

    Route::delete('/settings/account', [AccountController::class, 'destroy'])
        ->name('settings.account.destroy');

    Route::get('/settings/usage', [UsageController::class, 'index'])
        ->name('settings.usage');

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

        Route::get('/streamlabs', [StreamLabsIntegrationController::class, 'show'])->name('streamlabs.show');
        Route::get('/streamlabs/redirect', [StreamLabsIntegrationController::class, 'redirect'])->name('streamlabs.redirect');
        Route::patch('/streamlabs/test-mode', [StreamLabsIntegrationController::class, 'setTestMode'])->name('streamlabs.test-mode');
        Route::post('/streamlabs/seed-count', [StreamLabsIntegrationController::class, 'seedDonationCount'])->name('streamlabs.seed-count');
        Route::delete('/streamlabs', [StreamLabsIntegrationController::class, 'disconnect'])->name('streamlabs.disconnect');

        Route::get('/streamelements', [StreamElementsIntegrationController::class, 'show'])->name('streamelements.show');
        Route::post('/streamelements', [StreamElementsIntegrationController::class, 'save'])->name('streamelements.save');
        Route::patch('/streamelements/test-mode', [StreamElementsIntegrationController::class, 'setTestMode'])->name('streamelements.test-mode');
        Route::post('/streamelements/seed-count', [StreamElementsIntegrationController::class, 'seedDonationCount'])->name('streamelements.seed-count');
        Route::delete('/streamelements', [StreamElementsIntegrationController::class, 'disconnect'])->name('streamelements.disconnect');

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

        Route::patch('/bot', [BotSettingsController::class, 'setEnabled'])->name('bot.enabled');
    });

    // Bot Expressions: user-authored chat commands templated against controls + Helix.
    Route::prefix('settings/bot/expressions')->name('settings.bot.expressions.')->group(function () {
        Route::get('/', [BotExpressionsController::class, 'index'])->name('index');
        Route::get('/create', [BotExpressionsController::class, 'create'])->name('create');
        Route::post('/', [BotExpressionsController::class, 'store'])->name('store');
        Route::post('/preview', [BotExpressionsController::class, 'preview'])->name('preview');
        Route::get('/{botExpression}/edit', [BotExpressionsController::class, 'edit'])->name('edit');
        Route::patch('/{botExpression}', [BotExpressionsController::class, 'update'])->name('update');
        Route::delete('/{botExpression}', [BotExpressionsController::class, 'destroy'])->name('destroy');
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
