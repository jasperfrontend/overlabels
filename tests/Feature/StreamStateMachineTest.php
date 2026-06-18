<?php

use App\Events\StreamStatusChanged;
use App\Models\StreamSession;
use App\Models\StreamState;
use App\Models\User;
use App\Services\StreamStateMachineService;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Build the state machine with Twitch API + EventSub mocks bound, and a user
 * sitting in the "starting" state one verification short of the live threshold.
 * The next confident Helix check tips it over into transitionToLive().
 */
function bootStateMachineAtStarting(string $helixStartedAt, string $helixStreamId = '987654321'): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    StreamState::create([
        'user_id' => $user->id,
        'state' => StreamState::STATE_STARTING,
        'confidence' => 0.50, // one +0.25 confident check away from the 0.75 threshold
        'last_event_at' => now(),
    ]);

    $eventSub = Mockery::mock(TwitchEventSubService::class);
    $eventSub->shouldReceive('getAppAccessToken')->andReturn('fake-app-token');

    $twitchApi = Mockery::mock(TwitchApiService::class);
    $twitchApi->shouldReceive('getStreamStatus')->andReturn([
        'is_live' => true,
        'stream' => [
            'id' => $helixStreamId,
            'started_at' => $helixStartedAt,
            'game_name' => 'Just Chatting',
            'title' => 'test stream',
            'viewer_count' => 42,
        ],
    ]);

    app()->instance(TwitchEventSubService::class, $eventSub);
    app()->instance(TwitchApiService::class, $twitchApi);

    return [$user, app(StreamStateMachineService::class)];
}

it('repairs session started_at to the Helix go-live time on live transition', function () {
    Queue::fake();
    Event::fake([StreamStatusChanged::class]);

    // Twitch says the stream actually went live 11 minutes ago - the classic
    // EventSub-online lag that the retroactive repair exists to erase.
    $helixStartedAt = now()->subMinutes(11)->startOfSecond();

    [$user, $machine] = bootStateMachineAtStarting($helixStartedAt->toIso8601String());

    $machine->verify($user);

    $state = StreamState::forUser($user);
    expect($state->state)->toBe(StreamState::STATE_LIVE)
        ->and($state->confidence)->toBeGreaterThanOrEqual(StreamState::CONFIDENCE_THRESHOLD);

    $session = StreamSession::find($state->current_session_id);
    expect($session)->not->toBeNull();

    // The bug: started_at stayed at openSession's now() (~11 min late) because the
    // signed diffInSeconds guard was always negative. After the fix it snaps back
    // to Helix truth, matching twitch.tv's uptime to within a second.
    expect($session->started_at->diffInSeconds($helixStartedAt, true))
        ->toBeLessThanOrEqual(2);
});

it('broadcasts the Helix-aligned started_at, not the late live-transition time', function () {
    Queue::fake();
    Event::fake([StreamStatusChanged::class]);

    $helixStartedAt = now()->subMinutes(11)->startOfSecond();

    [$user, $machine] = bootStateMachineAtStarting($helixStartedAt->toIso8601String());

    $machine->verify($user);

    Event::assertDispatched(StreamStatusChanged::class, function (StreamStatusChanged $event) use ($helixStartedAt) {
        return $event->live === true
            && $event->startedAt !== null
            && Carbon::parse($event->startedAt)->diffInSeconds($helixStartedAt, true) <= 2;
    });
});
