<?php

use App\Http\Controllers\Admin\AdminAccessLogController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTemplateTagController;
use App\Http\Controllers\Admin\AdminAccessTokenController;
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

        // Templates
        Route::get('/templates', [AdminTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/{template}', [AdminTemplateController::class, 'show'])->name('templates.show');
        Route::patch('/templates/{template}', [AdminTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [AdminTemplateController::class, 'destroy'])->name('templates.destroy');

        // Events
        Route::get('/events', [AdminTwitchEventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [AdminTwitchEventController::class, 'show'])->name('events.show');
        Route::patch('/events/{event}', [AdminTwitchEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [AdminTwitchEventController::class, 'destroy'])->name('events.destroy');

        // Tags — categories must come before {tag} to avoid route conflict
        Route::get('/tags/categories', [AdminTemplateTagController::class, 'indexCategories'])->name('tags.categories.index');
        Route::patch('/tags/categories/{category}', [AdminTemplateTagController::class, 'updateCategory'])->name('tags.categories.update');
        Route::delete('/tags/categories/{category}', [AdminTemplateTagController::class, 'destroyCategory'])->name('tags.categories.destroy');
        Route::get('/tags', [AdminTemplateTagController::class, 'index'])->name('tags.index');
        Route::patch('/tags/{tag}', [AdminTemplateTagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [AdminTemplateTagController::class, 'destroy'])->name('tags.destroy');

        // Tokens
        Route::get('/tokens', [AdminAccessTokenController::class, 'index'])->name('tokens.index');
        Route::get('/tokens/{token}', [AdminAccessTokenController::class, 'show'])->name('tokens.show');
        Route::delete('/tokens/{token}', [AdminAccessTokenController::class, 'destroy'])->name('tokens.destroy');

        // Sessions
        Route::get('/sessions', [AdminSessionController::class, 'index'])->name('sessions.index');
        Route::delete('/sessions/{session}', [AdminSessionController::class, 'destroy'])->name('sessions.destroy');

        // Logs
        Route::get('/logs', [AdminAccessLogController::class, 'index'])->name('logs.index');
        Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit.index');

        // Impersonation
        Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
        Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    });
