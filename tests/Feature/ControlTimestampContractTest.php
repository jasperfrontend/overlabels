<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\StreamState;
use App\Models\User;
use App\Services\External\ExternalControlService;
use App\Services\StreamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `_at` answers "when did this control last receive a write", NOT "when did its
 * value last change".
 *
 * The distinction is invisible until two sources are raced against each other in
 * an Expression Control, and then it decides the answer. `latest()` compares
 * `_at` values, so a control whose timestamp freezes on a repeat value makes its
 * whole service look stale: a donor tipping twice in a row, the same viewer
 * cheering twice, a service re-sending an identical payload.
 *
 * It froze for two separate reasons, and both are pinned here:
 *
 *   1. Eloquent skips the UPDATE entirely when nothing is dirty, so
 *      `update(['value' => $same])` never moved `updated_at`. That is what
 *      OverlayControl::writeValue() exists to defeat, and every write path now
 *      goes through it.
 *   2. ExternalControlService dropped an unchanged value from persistence AND
 *      the broadcast. It now only drops a sub-threshold change on a key with a
 *      POSITIVE configured epsilon - noise suppression for GPS floats.
 *
 * A prod control sat 25 days stale under (2) while donations arrived minutes
 * apart, which is the bug that started all this.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
});

function managedControl(User $user, string $source, string $key, string $type, string $value): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'label' => $key,
        'type' => $type,
        'value' => $value,
        'source' => $source,
        'source_managed' => true,
    ]);
}

/** Backdate a control so a fresh write is unambiguously newer. */
function backdate(OverlayControl $control, int $days = 25): void
{
    $control->forceFill(['updated_at' => now()->subDays($days)])->saveQuietly();
}

it('moves updated_at when the value written is identical', function () {
    $control = managedControl($this->user, 'kofi', 'latest_donor_name', 'text', 'Jo Example');
    backdate($control);
    $before = $control->fresh()->updated_at;

    $control->writeValue('Jo Example');

    expect($control->fresh()->updated_at->greaterThan($before))->toBeTrue()
        ->and($control->fresh()->value)->toBe('Jo Example');
});

it('does not move updated_at on a plain Eloquent update, which is why writeValue exists', function () {
    // Documents the framework behaviour this whole contract works around. If
    // this ever starts failing, Eloquent changed and writeValue can be revisited.
    $control = managedControl($this->user, 'kofi', 'latest_donor_name', 'text', 'Jo Example');
    backdate($control);
    $before = $control->fresh()->updated_at;

    $control->update(['value' => 'Jo Example']);

    expect($control->fresh()->updated_at->equalTo($before))->toBeTrue();
});

it('moves updated_at when a donation repeats the same donor name', function () {
    ExternalIntegration::factory()->create([
        'user_id' => $this->user->id,
        'service' => 'kofi',
        'enabled' => true,
    ]);
    $name = managedControl($this->user, 'kofi', 'latest_donor_name', 'text', 'Jo Example');
    backdate($name);
    $before = $name->fresh()->updated_at;

    app(ExternalControlService::class)->applyUpdates($this->user, 'kofi', [
        'latest_donor_name' => 'Jo Example',
    ]);

    expect($name->fresh()->updated_at->greaterThan($before))->toBeTrue();
});

it('lets a repeat donation win the latest() race against an older service', function () {
    // The exact prod shape: Ko-fi's name control was 25 days stale, so racing
    // latest_donor_name_at named Ko-fi even though Streamlabs had just paid.
    $kofi = managedControl($this->user, 'kofi', 'latest_donor_name', 'text', 'Jo Example');
    $streamlabs = managedControl($this->user, 'streamlabs', 'latest_donor_name', 'text', 'Kevin');
    backdate($kofi, 25);
    backdate($streamlabs, 26);

    app(ExternalControlService::class)->applyUpdates($this->user, 'streamlabs', [
        'latest_donor_name' => 'Kevin',
    ]);

    expect($streamlabs->fresh()->updated_at->greaterThan($kofi->fresh()->updated_at))->toBeTrue();
});

it('moves updated_at when the same viewer cheers twice in a row', function () {
    $name = managedControl($this->user, 'twitch', 'latest_cheerer_name', 'text', '');

    app(StreamSessionService::class)->handleEvent($this->user, 'channel.cheer', [
        'bits' => 100, 'user_name' => 'marijke', 'message' => 'one',
    ]);
    $first = $name->fresh()->updated_at;
    backdate($name, 1);

    app(StreamSessionService::class)->handleEvent($this->user, 'channel.cheer', [
        'bits' => 100, 'user_name' => 'marijke', 'message' => 'one',
    ]);

    expect($name->fresh()->value)->toBe('marijke')
        ->and($name->fresh()->updated_at->greaterThan(now()->subMinute()))->toBeTrue()
        ->and($first)->not->toBeNull();
});

it('still suppresses a sub-threshold GPS drift, on both the value and the timestamp', function () {
    // lat carries a positive epsilon of 1e-5 (~1.1m). A parked device is
    // already silent - the phone stops transmitting - so this is the backstop,
    // and it must keep the stored value from creeping.
    ExternalIntegration::factory()->create([
        'user_id' => $this->user->id,
        'service' => 'gps',
        'enabled' => true,
    ]);
    $lat = managedControl($this->user, 'gps', 'lat', 'text', '52.3702000');
    backdate($lat);
    $before = $lat->fresh()->updated_at;

    app(ExternalControlService::class)->applyUpdates($this->user, 'gps', [
        'lat' => '52.3702001',
    ]);

    expect($lat->fresh()->value)->toBe('52.3702000', 'drift must not creep into the stored value')
        ->and($lat->fresh()->updated_at->equalTo($before))->toBeTrue();
});

it('accepts a GPS movement above the threshold', function () {
    ExternalIntegration::factory()->create([
        'user_id' => $this->user->id,
        'service' => 'gps',
        'enabled' => true,
    ]);
    $lat = managedControl($this->user, 'gps', 'lat', 'text', '52.3702000');
    backdate($lat);
    $before = $lat->fresh()->updated_at;

    app(ExternalControlService::class)->applyUpdates($this->user, 'gps', [
        'lat' => '52.3712000',
    ]);

    expect($lat->fresh()->value)->toBe('52.3712000')
        ->and($lat->fresh()->updated_at->greaterThan($before))->toBeTrue();
});

it('treats a zero epsilon as no suppression at all', function () {
    // `distance` is configured at 0.0, which used to read as "exact compare".
    // Only a POSITIVE threshold suppresses now, so 0.0 behaves like any other
    // unlisted key and an identical write still lands.
    expect(config('controls.change_detection.epsilon.distance'))->toBe(0.0);

    ExternalIntegration::factory()->create([
        'user_id' => $this->user->id,
        'service' => 'gps',
        'enabled' => true,
    ]);
    $distance = managedControl($this->user, 'gps', 'distance', 'number', '12');
    backdate($distance);
    $before = $distance->fresh()->updated_at;

    app(ExternalControlService::class)->applyUpdates($this->user, 'gps', [
        'distance' => ['action' => 'add', 'amount' => 0],
    ]);

    expect($distance->fresh()->updated_at->greaterThan($before))->toBeTrue();
});

it('moves updated_at when a go-live reset writes zero over an already-zero counter', function () {
    // A reset is a write, so it moves the timestamp like any other. Nothing
    // races a per-stream counter's _at, and the latest_* controls that WERE
    // caught by this are no longer reset at all.
    StreamState::updateOrCreate(
        ['user_id' => $this->user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 1.0],
    );
    $follows = managedControl($this->user, 'twitch', 'follows_this_stream', 'counter', '0');
    backdate($follows);
    $before = $follows->fresh()->updated_at;

    app(StreamSessionService::class)->openSession($this->user);

    expect($follows->fresh()->value)->toBe('0')
        ->and($follows->fresh()->updated_at->greaterThan($before))->toBeTrue();
});
