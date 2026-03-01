<?php

use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\KofiIntegrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.redirect')->group(function () {
    Route::get('/settings/appearance', function () {
        return inertia('settings/Appearance');
    })->name('settings.appearance');

    // External Integrations
    Route::prefix('settings/integrations')->name('settings.integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::get('/kofi', [KofiIntegrationController::class, 'show'])->name('kofi.show');
        Route::post('/kofi', [KofiIntegrationController::class, 'save'])->name('kofi.save');
        Route::patch('/kofi/test-mode', [KofiIntegrationController::class, 'setTestMode'])->name('kofi.test-mode');
        Route::post('/kofi/seed-count', [KofiIntegrationController::class, 'seedDonationCount'])->name('kofi.seed-count');
        Route::delete('/kofi', [KofiIntegrationController::class, 'disconnect'])->name('kofi.disconnect');
    });
});
