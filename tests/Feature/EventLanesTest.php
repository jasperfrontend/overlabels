<?php

use App\Jobs\FlushAlertBurst;
use App\Jobs\ProcessTwitchEvent;
use Symfony\Component\Yaml\Yaml;

/**
 * Twitch events run on LANES queues, one worker each (#343). A streamer always
 * lands on the same lane, so their events keep Twitch's order; different
 * streamers run side by side.
 *
 * The arrangement lives in two places, and both ways of getting it wrong are
 * silent: a lane without a worker gives its streamers no alerts at all, and a
 * lane with two workers handles one streamer's events out of order (a gift
 * bomb's recipients before the gift). So config/deploy.yml is held to
 * ProcessTwitchEvent::queues() here.
 */

/** @return array<string, list<string>> role => the queues its worker reads */
function laneWorkerQueues(): array
{
    $out = [];
    foreach (Yaml::parseFile(base_path('config/deploy.yml'))['servers'] as $role => $config) {
        $cmd = is_array($config) ? ($config['cmd'] ?? '') : '';
        if (! str_contains($cmd, 'queue:work')) {
            continue;
        }
        $out[$role] = preg_match('/--queue=(\S+)/', $cmd, $m) ? explode(',', $m[1]) : ['default'];
    }

    return $out;
}

test('lane 0 keeps the queue name from before lanes, so jobs queued then still drain', function () {
    expect(ProcessTwitchEvent::queues())->toBe(['twitch-events', 'twitch-events-1', 'twitch-events-2', 'twitch-events-3'])
        ->and(ProcessTwitchEvent::queues()[0])->toBe(ProcessTwitchEvent::QUEUE);
});

test('every lane has exactly one worker in config/deploy.yml', function () {
    $workers = laneWorkerQueues();

    foreach (ProcessTwitchEvent::queues() as $lane) {
        $readers = array_keys(array_filter($workers, fn (array $queues) => in_array($lane, $queues, true)));
        expect($readers)->toHaveCount(1, "{$lane} is read by: ".implode(', ', $readers));
    }
});

test('a lane worker reads its lane and nothing else', function () {
    $lanes = ProcessTwitchEvent::queues();

    foreach (laneWorkerQueues() as $role => $queues) {
        if (array_intersect($queues, $lanes) !== []) {
            expect($queues)->toHaveCount(1, "{$role} reads more than its lane");
        }
    }
});

test('a streamer always gets the same lane', function () {
    // Pinned so a change to the hashing is a deliberate one: during a deploy
    // that moves streamers between lanes, their old and new lane briefly run
    // at once and their events can be handled out of order.
    expect(ProcessTwitchEvent::queueFor('73327367'))->toBe('twitch-events')
        ->and(ProcessTwitchEvent::queueFor('1303798109'))->toBe('twitch-events-1')
        ->and(ProcessTwitchEvent::queueFor('5'))->toBe('twitch-events-2')
        ->and(ProcessTwitchEvent::queueFor('100000001'))->toBe('twitch-events-3')
        ->and(ProcessTwitchEvent::queueFor(null))->toBe('twitch-events')
        ->and(ProcessTwitchEvent::queueFor(''))->toBe('twitch-events');
});

test('an event is queued on its broadcaster\'s lane, a raid on the receiving channel\'s', function () {
    $follow = new ProcessTwitchEvent(1, ['subscription' => ['type' => 'channel.follow'], 'event' => ['broadcaster_user_id' => '1303798109']]);
    $raid = new ProcessTwitchEvent(2, ['subscription' => ['type' => 'channel.raid'], 'event' => [
        'from_broadcaster_user_id' => '73327367', 'to_broadcaster_user_id' => '100000001',
    ]]);

    expect($follow->queue)->toBe('twitch-events-1')
        ->and($raid->queue)->toBe('twitch-events-3');
});

test('a burst flush runs on the same lane as the events that join it', function () {
    $data = ['subscription' => ['type' => 'channel.follow'], 'event' => ['broadcaster_user_id' => '5']];

    expect((new FlushAlertBurst(1, 'channel.follow', 1, $data))->queue)
        ->toBe((new ProcessTwitchEvent(1, $data))->queue)
        ->toBe('twitch-events-2');
});
