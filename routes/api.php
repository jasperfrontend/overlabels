<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitchEventSubController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\OverlayTemplateController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('/overlay')->group(function () {
    Route::post('/render', [OverlayTemplateController::class, 'renderAuthenticated'])
        ->name('api.overlay.render')
        ->middleware(['throttle:overlay', 'rate.limit.overlay'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
});

// Get all template tags (API endpoint)
Route::get('/template-tags', [TemplateTagController::class, 'getAllTags'])
    ->name('tags.api.all')
    ->middleware('auth:sanctum');

// Get job status for template tag operations
Route::get('/template-tags/jobs/{jobType?}', [TemplateTagController::class, 'getJobStatus'])
    ->name('tags.api.jobs')
    ->middleware('auth:sanctum');

// Twitch webhook endpoint - must be accessible without authentication or CSRF
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);

// EventSub health check endpoint for external cron services
Route::get('/eventsub-health-check', function () {
    try {
        $manager = app(\App\Services\UserEventSubManager::class);
        $stats = $manager->getGlobalStats();
        
        // Get failed subscriptions
        $failedSubs = \App\Models\UserEventsubSubscription::whereIn('status', [
            'webhook_callback_verification_failed',
            'notification_failures_exceeded',
            'authorization_revoked',
            'user_removed',
        ])->with('user')->get();
        
        // Auto-fix if there are failed subscriptions
        if ($failedSubs->count() > 0) {
            foreach ($failedSubs->groupBy('user_id') as $userId => $userFailedSubs) {
                $user = $userFailedSubs->first()->user;
                \App\Jobs\SetupUserEventSubSubscriptions::dispatch($user, true);
            }
        }
        
        // Check for users who should be connected but aren't
        $usersNeedingSetup = \App\Models\User::where('eventsub_auto_connect', true)
            ->whereNull('eventsub_connected_at')
            ->get();
            
        foreach ($usersNeedingSetup as $user) {
            \App\Jobs\SetupUserEventSubSubscriptions::dispatch($user, false);
        }
        
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'stats' => $stats,
            'actions_taken' => [
                'failed_subscriptions_renewed' => $failedSubs->count(),
                'users_auto_setup' => $usersNeedingSetup->count(),
            ],
        ]);
        
    } catch (Exception $e) {
        \Log::error('EventSub health check failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'status' => 'error',
            'timestamp' => now()->toISOString(),
            'error' => $e->getMessage(),
        ], 500);
    }
})->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
