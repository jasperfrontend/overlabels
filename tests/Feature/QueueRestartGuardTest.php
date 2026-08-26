<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * routes/console.php used to schedule `queue:restart` every five minutes,
 * gated on `cache('last_job_processed_at')` being older than ten minutes.
 * Nothing ever wrote that key, so the guard never fired - and had it worked,
 * it would have restarted the worker on every quiet night with no events.
 * Worker liveness belongs to Docker (`restart=unless-stopped` on the queue
 * container); the app does not restart its own worker on a heuristic.
 */
test('the app does not schedule a queue restart on a liveness heuristic', function () {
    $restarts = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'queue:restart'));

    expect($restarts)->toBeEmpty();
});
