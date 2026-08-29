<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\StreamState;
use App\Models\User;
use App\Services\StreamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * `cheers_received` / `bits_received` are the all-time twins of
 * `cheers_this_stream` / `bits_this_stream`, added so a streamer can put
 * "tonight" and "ever" on screen at once - a bits-only progress bar is the
 * motivating case.
 *
 * Two properties make them what they are, and both are easy to break by
 * tidying handleEvent():
 *
 *   1. They count while the channel is OFFLINE. Viewers cheer in offline chat,
 *      and no external donation driver consults stream state either.
 *   2. They survive the go-live reset, because they are not per-stream.
 *
 * The `latest_cheer*` trio is ungated for the same reason - a cheer that
 * arrives offline is still the latest cheer, and the donation services record
 * their `latest_donor_*` values with no live check anywhere.
 *
 * The per-stream keys must keep doing the exact opposite, or the two sets stop
 * meaning different things and comparing them is pointless.
 */
function cheerControl(User $user, string $key, string $type = 'counter', string $value = '0'): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'label' => $key,
        'type' => $type,
        'value' => $value,
        'source' => 'twitch',
        'source_managed' => true,
    ]);
}

function cheer(User $user, int $bits = 100, string $name = 'alice'): void
{
    app(StreamSessionService::class)->handleEvent($user, 'channel.cheer', [
        'bits' => $bits,
        'user_name' => $name,
        'message' => 'have some bits',
    ]);
}

function goLiveConfidently(User $user): void
{
    StreamState::updateOrCreate(
        ['user_id' => $user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 1.0],
    );
}

beforeEach(function () {
    Event::fake([ControlValueUpdated::class]);

    $this->user = User::factory()->create();
});

it('counts cheers and bits while the channel is offline', function () {
    $cheers = cheerControl($this->user, 'cheers_received');
    $bits = cheerControl($this->user, 'bits_received', 'number');

    // No StreamState row at all - the channel has never been seen live.
    cheer($this->user, bits: 250);

    expect($cheers->fresh()->value)->toBe('1')
        ->and($bits->fresh()->value)->toBe('250');
});

it('counts cheers and bits when the live state is not confident', function () {
    StreamState::updateOrCreate(
        ['user_id' => $this->user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 0.25],
    );

    $cheers = cheerControl($this->user, 'cheers_received');
    $bits = cheerControl($this->user, 'bits_received', 'number');

    cheer($this->user, bits: 40);

    expect($cheers->fresh()->value)->toBe('1')
        ->and($bits->fresh()->value)->toBe('40');
});

it('records the latest cheer while the channel is offline', function () {
    // A cheer that arrives offline is still the latest cheer. Every donation
    // service records its latest_donor_* the same way, with no live gate
    // anywhere in the external pipeline.
    $name = cheerControl($this->user, 'latest_cheerer_name', 'text', '');
    $amount = cheerControl($this->user, 'latest_cheer_amount', 'number');
    $message = cheerControl($this->user, 'latest_cheer_message', 'text', '');

    cheer($this->user, bits: 75, name: 'marijke');

    expect($name->fresh()->value)->toBe('marijke')
        ->and($amount->fresh()->value)->toBe('75')
        ->and($message->fresh()->value)->toBe('have some bits');
});

it('names an anonymous offline cheerer Anonymous rather than blank', function () {
    $name = cheerControl($this->user, 'latest_cheerer_name', 'text', '');

    app(StreamSessionService::class)->handleEvent($this->user, 'channel.cheer', [
        'bits' => 10,
        'is_anonymous' => true,
        'user_name' => 'should_be_ignored',
    ]);

    expect($name->fresh()->value)->toBe('Anonymous');
});

it('leaves the per-stream pair alone while offline', function () {
    $cheersEver = cheerControl($this->user, 'cheers_received');
    $cheersTonight = cheerControl($this->user, 'cheers_this_stream');
    $bitsTonight = cheerControl($this->user, 'bits_this_stream', 'number');

    cheer($this->user, bits: 500);

    expect($cheersEver->fresh()->value)->toBe('1', 'all-time counts offline')
        ->and($cheersTonight->fresh()->value)->toBe('0', 'per-stream must not count offline')
        ->and($bitsTonight->fresh()->value)->toBe('0', 'per-stream must not count offline');
});

it('moves both pairs together while confidently live', function () {
    goLiveConfidently($this->user);

    $cheersEver = cheerControl($this->user, 'cheers_received');
    $bitsEver = cheerControl($this->user, 'bits_received', 'number');
    $cheersTonight = cheerControl($this->user, 'cheers_this_stream');
    $bitsTonight = cheerControl($this->user, 'bits_this_stream', 'number');

    cheer($this->user, bits: 300);

    expect($cheersEver->fresh()->value)->toBe('1')
        ->and($bitsEver->fresh()->value)->toBe('300')
        ->and($cheersTonight->fresh()->value)->toBe('1')
        ->and($bitsTonight->fresh()->value)->toBe('300');
});

it('accumulates across several cheers', function () {
    $cheers = cheerControl($this->user, 'cheers_received');
    $bits = cheerControl($this->user, 'bits_received', 'number');

    cheer($this->user, bits: 100);
    cheer($this->user, bits: 250);
    cheer($this->user, bits: 1);

    expect($cheers->fresh()->value)->toBe('3')
        ->and($bits->fresh()->value)->toBe('351');
});

it('survives the go-live reset that zeroes the per-stream pair', function () {
    $cheersEver = cheerControl($this->user, 'cheers_received', 'counter', '812');
    $bitsEver = cheerControl($this->user, 'bits_received', 'number', '90210');
    $cheersTonight = cheerControl($this->user, 'cheers_this_stream', 'counter', '12');
    $bitsTonight = cheerControl($this->user, 'bits_this_stream', 'number', '3400');

    app(StreamSessionService::class)->openSession($this->user);

    expect($cheersEver->fresh()->value)->toBe('812', 'all-time must survive go-live')
        ->and($bitsEver->fresh()->value)->toBe('90210', 'all-time must survive go-live')
        ->and($cheersTonight->fresh()->value)->toBe('0')
        ->and($bitsTonight->fresh()->value)->toBe('0');
});

it('keeps the all-time keys off the per-stream reset list', function () {
    expect(StreamSessionService::PER_STREAM_CONTROL_KEYS)
        ->not->toContain('cheers_received')
        ->not->toContain('bits_received');
});

it('offers both keys as presets the add-preset flow can resolve', function () {
    // OverlayControlController::store() resolves a twitch preset out of
    // CONTROL_PRESETS and 422s on anything missing, so a key the picker offers
    // but this list lacks is a dead row in the modal.
    $presetKeys = collect(StreamSessionService::CONTROL_PRESETS)->pluck('key');

    expect($presetKeys)->toContain('cheers_received')
        ->and($presetKeys)->toContain('bits_received');
});

it('ignores a zero-bit cheer for the amount but still counts the transaction', function () {
    $cheers = cheerControl($this->user, 'cheers_received');
    $bits = cheerControl($this->user, 'bits_received', 'number');

    cheer($this->user, bits: 0);

    // Why this matters: cheers_received always moves, bits_received does not,
    // so cheers_received_at is the honest "when did a cheer last arrive"
    // timestamp to race in latest(). See the latest-donator tutorial.
    expect($cheers->fresh()->value)->toBe('1')
        ->and($bits->fresh()->value)->toBe('0');
});

it('does not touch the all-time pair on a non-cheer event', function () {
    goLiveConfidently($this->user);

    $cheers = cheerControl($this->user, 'cheers_received');
    $bits = cheerControl($this->user, 'bits_received', 'number');
    $follows = cheerControl($this->user, 'follows_this_stream');

    app(StreamSessionService::class)->handleEvent($this->user, 'channel.follow', []);

    expect($follows->fresh()->value)->toBe('1')
        ->and($cheers->fresh()->value)->toBe('0')
        ->and($bits->fresh()->value)->toBe('0');
});

it('never counts another user\'s cheer', function () {
    // Live, so this isolates user scoping rather than re-testing the gate.
    goLiveConfidently($this->user);

    $other = User::factory()->create();
    $mine = cheerControl($this->user, 'cheers_received');
    $theirs = cheerControl($other, 'cheers_received');

    cheer($this->user, bits: 100);

    expect($mine->fresh()->value)->toBe('1')
        ->and($theirs->fresh()->value)->toBe('0');
});
