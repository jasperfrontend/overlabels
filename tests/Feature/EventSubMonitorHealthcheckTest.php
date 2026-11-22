<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Psr\Http\Message\ResponseInterface;

/**
 * Dead-man's switch for the hourly eventsub:monitor run. The command crashed on
 * every run from April to November 2026 and nothing noticed, because a dead
 * monitor reports nothing. Healthchecks.io alerts on the ABSENCE of a ping, so
 * a monitor that stops running, crashes, or reports poor health (exit 1) is
 * flagged by something outside the app. Same shape as the backup's switch.
 */
function monitorScheduleEvent(): Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'eventsub:monitor'));
}

/** Swap Guzzle for a recorder; Event::getHttpClient() prefers this binding. */
function recordMonitorPings(): ArrayObject
{
    $seen = new ArrayObject;

    $client = new class($seen) extends Client
    {
        public function __construct(private ArrayObject $seen)
        {
            parent::__construct();
        }

        public function request(string $method, $uri = '', array $options = []): ResponseInterface
        {
            $this->seen[] = $uri;

            return new Response(200);
        }
    };

    app()->instance(Client::class, $client);

    return $seen;
}

it('pings the monitor healthcheck when the run succeeds', function () {
    $seen = recordMonitorPings();

    $event = monitorScheduleEvent();
    $event->exitCode = 0;
    $event->callAfterCallbacks(app());

    expect(iterator_to_array($seen))->toBe([config('services.eventsub_monitor.healthcheck_url')]);
});

it('pings the monitor /fail endpoint when the run fails', function () {
    $seen = recordMonitorPings();

    $event = monitorScheduleEvent();
    $event->exitCode = 1;
    $event->callAfterCallbacks(app());

    expect(iterator_to_array($seen))->toBe([config('services.eventsub_monitor.healthcheck_url').'/fail']);
});

it('uses its own check, never the backup one', function () {
    // Sharing a check would let a healthy backup mask a dead monitor and the
    // other way round.
    expect(config('services.eventsub_monitor.healthcheck_url'))
        ->not->toBeEmpty()
        ->not->toBe(config('services.backups.healthcheck_url'));
});
