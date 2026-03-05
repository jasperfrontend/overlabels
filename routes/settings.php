<?php

use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\KofiIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.redirect')->group(function () {
    Route::get('/settings/appearance', function (Request $request) {
        return inertia('settings/Appearance', [
            'userIcon' => $request->user()->icon ?? 'smile',
        ]);
    })->name('settings.appearance');

    Route::patch('/settings/icon', function (Request $request) {
        $request->validate(['icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/']]);
        $request->user()->update(['icon' => $request->input('icon') ?: null]);

        return back();
    })->name('settings.icon');

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
