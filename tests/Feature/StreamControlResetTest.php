<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\StreamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Create a source_managed twitch control carrying a non-default value, so a
 * reset is observable.
 */
function twitchControl(User $user, OverlayTemplate $template, string $key, string $value, string $type = 'counter'): OverlayControl
{
    return OverlayControl::factory()->create([
        'user_id' => $user->id,
        'overlay_template_id' => $template->id,
        'key' => $key,
        'type' => $type,
        'value' => $value,
        'source' => 'twitch',
        'source_managed' => true,
    ]);
}

beforeEach(function () {
    Event::fake([ControlValueUpdated::class]);

    $this->user = User::factory()->create();
    $this->template = OverlayTemplate::factory()->create(['owner_id' => $this->user->id]);
});

it('resets every per-stream counter when a session opens', function () {
    $controls = collect(StreamSessionService::PER_STREAM_CONTROL_KEYS)
        ->mapWithKeys(fn (string $key) => [
            $key => twitchControl($this->user, $this->template, $key, '42'),
        ]);

    app(StreamSessionService::class)->openSession($this->user);

    foreach ($controls as $key => $control) {
        expect($control->fresh()->value)->toBe('0', "$key should reset at go-live");
    }
});

it('leaves the latest_cheer* controls alone when a session opens', function () {
    // These carry source='twitch' but are most-recent values, not per-stream
    // tallies. Their equivalents on the donation services persist across
    // streams, and these must match that.
    $name = twitchControl($this->user, $this->template, 'latest_cheerer_name', 'alice', 'text');
    $amount = twitchControl($this->user, $this->template, 'latest_cheer_amount', '500', 'number');
    $message = twitchControl($this->user, $this->template, 'latest_cheer_message', 'have some bits', 'text');

    app(StreamSessionService::class)->openSession($this->user);

    expect($name->fresh()->value)->toBe('alice')
        ->and($amount->fresh()->value)->toBe('500')
        ->and($message->fresh()->value)->toBe('have some bits');
});

it('never writes the string "0" over a latest_cheerer_name', function () {
    // The specific regression: a reset_value of 0 on a text control put a
    // literal "0" in the label, which then won any latest() race across
    // services because its _at was the freshest timestamp on the board.
    $control = twitchControl($this->user, $this->template, 'latest_cheerer_name', 'alice', 'text');

    app(StreamSessionService::class)->openSession($this->user);

    expect($control->fresh()->value)->not->toBe('0');
});

it('does not touch the _at companion of a control it leaves alone', function () {
    // _at is updated_at, so a no-op reset must not bump it. This is what makes
    // latest() racing twitch against the donation services meaningful.
    $control = twitchControl($this->user, $this->template, 'latest_cheerer_name', 'alice', 'text');
    $before = $control->fresh()->updated_at;

    $this->travel(5)->minutes();
    app(StreamSessionService::class)->openSession($this->user);

    expect($control->fresh()->updated_at->timestamp)->toBe($before->timestamp);
});

it('leaves controls belonging to other sources alone', function () {
    $kofi = OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => $this->template->id,
        'key' => 'latest_donor_name',
        'type' => 'text',
        'value' => 'bob',
        'source' => 'kofi',
        'source_managed' => true,
    ]);

    app(StreamSessionService::class)->openSession($this->user);

    expect($kofi->fresh()->value)->toBe('bob');
});

it('leaves a user-created control alone even when its key matches a per-stream key', function () {
    // source_managed=false means the user owns this row, whatever it is called.
    $own = OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => $this->template->id,
        'key' => 'follows_this_stream',
        'type' => 'counter',
        'value' => '99',
        'source' => null,
        'source_managed' => false,
    ]);

    app(StreamSessionService::class)->openSession($this->user);

    expect($own->fresh()->value)->toBe('99');
});

it('leaves another users controls alone', function () {
    $other = User::factory()->create();
    $otherTemplate = OverlayTemplate::factory()->create(['owner_id' => $other->id]);
    $control = twitchControl($other, $otherTemplate, 'follows_this_stream', '7');

    app(StreamSessionService::class)->openSession($this->user);

    expect($control->fresh()->value)->toBe('7');
});

it('honours a configured reset_value instead of always zeroing', function () {
    $control = OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => $this->template->id,
        'key' => 'follows_this_stream',
        'type' => 'counter',
        'value' => '42',
        'config' => ['step' => 1, 'reset_value' => 10],
        'source' => 'twitch',
        'source_managed' => true,
    ]);

    app(StreamSessionService::class)->openSession($this->user);

    expect($control->fresh()->value)->toBe('10');
});

it('keeps every per-stream key pointed at a real preset', function () {
    $presetKeys = collect(StreamSessionService::CONTROL_PRESETS)->pluck('key');

    foreach (StreamSessionService::PER_STREAM_CONTROL_KEYS as $key) {
        expect($presetKeys)->toContain($key);
    }
});
