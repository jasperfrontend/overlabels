<?php

use App\Events\AlertTriggered;
use App\Events\TtsAudioReady;
use App\Events\TwitchEventReceived;
use App\Jobs\SynthesizeAlertTts;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Yaml\Yaml;

/**
 * Alerts go first (#343). Every broadcast is a queued job, and a raid puts
 * one per follow on `default` (the raw event, list and counter updates). An
 * alert, its TTS synthesis and its TtsAudioReady therefore ride their own
 * queue, which the `queue` worker empties before `default`.
 *
 * The arrangement has two halves in two places, and getting either wrong is
 * silent: an alert on a queue no worker reads never broadcasts, with no error
 * anywhere. So both halves are pinned here.
 */
test('an alert broadcast goes on the alerts queue', function () {
    Queue::fake();

    broadcast(new AlertTriggered('alert-1', '<div></div>', '', [], 5000, '123'));

    Queue::assertPushedOn(AlertTriggered::QUEUE, BroadcastEvent::class);
    expect(AlertTriggered::QUEUE)->toBe('alerts');
});

test('the voice of an alert goes on the alerts queue too', function () {
    Queue::fake();

    SynthesizeAlertTts::dispatch('alert-1', '123', 'Thanks for the follow!');
    broadcast(new TtsAudioReady('alert-1', '123', 'https://example.test/tts.mp3'));

    Queue::assertPushedOn(AlertTriggered::QUEUE, SynthesizeAlertTts::class);
    Queue::assertPushedOn(AlertTriggered::QUEUE, BroadcastEvent::class);
});

test('an event broadcast stays on the default queue', function () {
    Queue::fake();

    broadcast(new TwitchEventReceived('channel.follow', [], '123'));

    // No queue named = the connection's default.
    Queue::assertPushed(BroadcastEvent::class, fn ($job, $queue) => $queue !== AlertTriggered::QUEUE);
});

test('the prod queue worker reads the alerts queue before default', function () {
    $roles = Yaml::parseFile(base_path('config/deploy.yml'))['servers'];

    expect($roles['queue']['cmd'])->toContain('--queue=alerts,default');
});

test('the local dev worker reads the alerts queue too', function () {
    $scripts = json_decode(file_get_contents(base_path('composer.json')), true)['scripts'];
    $workers = collect($scripts)->flatten()->filter(fn ($line) => is_string($line) && str_contains($line, 'queue:'));

    expect($workers)->not->toBeEmpty()
        ->and($workers->every(fn ($line) => str_contains($line, '--queue=alerts,default')))->toBeTrue();
});
