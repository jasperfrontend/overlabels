<?php

use App\Events\StreamStatusChanged;
use App\Jobs\VerifyStreamState;
use App\Models\StreamSession;
use App\Models\StreamState;
use App\Models\User;
use App\Services\StreamStateMachineService;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

/**
 * The verification chain is the only thing that moves a stream from "starting"
 * to "live" in seconds rather than minutes: each VerifyStreamState queues the
 * next one 10 seconds out until confidence clears the threshold.
 *
 * That self-dispatch happens INSIDE handle(), so it runs while the job still
 * holds its own uniqueness lock. Under plain ShouldBeUnique the lock is only
 * released after handle() returns, so the successor never gets queued - it is
 * dropped silently, with no exception and no failed_jobs row. The chain dies on
 * its first link and the 5-minute safety net becomes the only thing advancing
 * the machine, which measured ~10 minutes to go live on production.
 *
 * These two tests run the job through the real worker path, because the release
 * ordering they pin lives in CallQueuedHandler and is invisible to Queue::fake().
 */
function runQueuedVerifyJob(User $user): void
{
    // Dispatch and execute with production's own timing: dispatched with a 10s
    // delay, picked up a second later. Both sit inside the 15s uniqueFor TTL,
    // which is precisely why the 10s chain stalls while the 60s heartbeat does
    // not - the heartbeat's lock has expired long before its job runs.
    VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
    expect(DB::table('jobs')->count())->toBe(1);

    fireNextQueuedJob();
}

/**
 * Advance past a 10-second delay and run whatever the queue hands back through
 * the real CallQueuedHandler. Still inside the 15s uniqueFor window.
 */
function fireNextQueuedJob(): void
{
    Carbon::setTestNow(Carbon::now()->addSeconds(11));

    $job = app('queue')->connection('database')->pop();
    expect($job)->not->toBeNull();

    $job->fire();
}

it('queues the next verification while the running job still holds its unique lock', function () {
    config(['queue.default' => 'database']);

    [$user] = bootStateMachineAtStarting(now()->toIso8601String());

    // 0.25 means a confident Helix check lands on 0.50 - short of the 0.75
    // threshold - so verifyStarting() takes its "check again in 10s" branch.
    StreamState::forUser($user)->update(['confidence' => 0.25]);

    runQueuedVerifyJob($user);

    $state = StreamState::forUser($user);
    expect($state->state)->toBe(StreamState::STATE_STARTING)
        ->and((float) $state->confidence)->toBe(0.50);

    // The original job deleted itself on completion, so anything left here is
    // the successor. Zero means the chain is broken.
    expect(DB::table('jobs')->count())->toBe(1);
});

it('reaches live in one more verification instead of waiting for the safety net', function () {
    config(['queue.default' => 'database']);

    Event::fake([StreamStatusChanged::class]);

    [$user] = bootStateMachineAtStarting(now()->toIso8601String());
    StreamState::forUser($user)->update(['confidence' => 0.25]);

    // First link: 0.25 -> 0.50, and queues the second.
    runQueuedVerifyJob($user);

    // Second link, running off the queue exactly as the first one left it.
    fireNextQueuedJob();

    // ~22 seconds of wall clock from the first dispatch, against the ~10 minutes
    // production actually took on both of the 2026-08-29 streams.
    $state = StreamState::forUser($user);
    expect($state->state)->toBe(StreamState::STATE_LIVE)
        ->and((float) $state->confidence)->toBeGreaterThanOrEqual(StreamState::CONFIDENCE_THRESHOLD);
});
