<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// EventSub Health Monitoring - runs every hour
Schedule::command('eventsub:monitor --fix')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/eventsub-monitor.log'));

// Deep health check - runs every 6 hours
Schedule::command('eventsub:monitor --fix')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/eventsub-deep-check.log'));

// Queue monitoring - runs every 5 minutes (in case queue worker stops)
Schedule::command('queue:restart')
    ->everyFiveMinutes()
    ->when(function () {
        // Only restart if queue seems stuck (no jobs processed in 10 minutes)
        $lastJob = cache()->get('last_job_processed_at');

        return $lastJob && now()->diffInMinutes($lastJob) > 10;
    });

// Prune Telescope entries older than 48 hours - keeps the DB from ballooning
Schedule::command('telescope:prune --hours=48')
    ->daily();

// Auto-prune high-volume log/event tables weekly (keeps last 90 days)
Schedule::call(fn () => \App\Models\OverlayAccessLog::where('accessed_at', '<', now()->subDays(90))->delete())
    ->weekly()->name('prune:access-logs')->withoutOverlapping();

Schedule::call(fn () => \App\Models\TwitchEvent::where('created_at', '<', now()->subDays(90))->delete())
    ->weekly()->name('prune:twitch-events')->withoutOverlapping();

Schedule::call(fn () => \App\Models\ExternalEvent::where('created_at', '<', now()->subDays(90))->delete())
    ->weekly()->name('prune:external-events')->withoutOverlapping();

// Cleanup old logs - runs daily
Schedule::command('log:clear')
    ->daily()
    ->onFailure(function () {
        // Log cleanup failure if needed
    });
