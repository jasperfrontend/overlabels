<?php

use App\Http\Controllers\Settings\BMACIntegrationController;
use App\Http\Controllers\Settings\BotSettingsController;
use App\Http\Controllers\Settings\FourthwallIntegrationController;
use App\Http\Controllers\Settings\GpsLoggerIntegrationController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\KofiIntegrationController;
use App\Http\Controllers\Settings\OverlabelsMobileIntegrationController;
use App\Http\Controllers\Settings\StreamElementsIntegrationController;
use App\Http\Controllers\Settings\StreamLabsIntegrationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.redirect')->group(function () {
    Route::get('/settings/appearance', function () {
        return inertia('settings/Appearance');
    })->name('settings.appearance');

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

        Route::get('/gpslogger', [GpsLoggerIntegrationController::class, 'show'])->name('gpslogger.show');
        Route::post('/gpslogger', [GpsLoggerIntegrationController::class, 'save'])->name('gpslogger.save');
        Route::delete('/gpslogger', [GpsLoggerIntegrationController::class, 'disconnect'])->name('gpslogger.disconnect');
        Route::post('/gpslogger/reset-distance', [GpsLoggerIntegrationController::class, 'resetDistance'])->name('gpslogger.reset-distance');

        Route::get('/overlabels-mobile', [OverlabelsMobileIntegrationController::class, 'show'])->name('overlabels-mobile.show');
        Route::post('/overlabels-mobile', [OverlabelsMobileIntegrationController::class, 'save'])->name('overlabels-mobile.save');
        Route::post('/overlabels-mobile/regenerate-token', [OverlabelsMobileIntegrationController::class, 'regenerateToken'])->name('overlabels-mobile.regenerate-token');
        Route::delete('/overlabels-mobile', [OverlabelsMobileIntegrationController::class, 'disconnect'])->name('overlabels-mobile.disconnect');
        Route::post('/overlabels-mobile/reset-distance', [OverlabelsMobileIntegrationController::class, 'resetDistance'])->name('overlabels-mobile.reset-distance');
        Route::post('/overlabels-mobile/clear-safe-zone', [OverlabelsMobileIntegrationController::class, 'clearSafeZone'])->name('overlabels-mobile.clear-safe-zone');

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
});
