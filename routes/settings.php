<?php

use App\Http\Controllers\Settings\BotSettingsController;
use App\Http\Controllers\Settings\GpsLoggerIntegrationController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\KofiIntegrationController;
use App\Http\Controllers\Settings\StreamElementsIntegrationController;
use App\Http\Controllers\Settings\StreamLabsIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.redirect')->group(function () {
    Route::get('/settings/appearance', function () {
        return inertia('settings/Appearance');
    })->name('settings.appearance');

    Route::patch('/settings/locale', function (Request $request) {
        $request->validate(['locale' => 'required|string|max:10']);
        $request->user()->update(['locale' => $request->input('locale')]);

        return back();
    })->name('settings.locale');

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

        Route::patch('/bot', [BotSettingsController::class, 'setEnabled'])->name('bot.enabled');
    });
});
