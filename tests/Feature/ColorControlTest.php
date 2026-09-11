<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->template = OverlayTemplate::factory()->create(['owner_id' => $this->user->id]);
    $this->actingAs($this->user);
});

/**
 * Color values the picker cannot read but CSS can. These are the whole reason
 * the control does not validate: Reka's parser handles hex, comma-separated
 * rgb()/hsl()/hsb() and nothing else, while a template author can legitimately
 * write any of these into a custom property.
 */
dataset('css colors the picker cannot parse', [
    'named' => ['rebeccapurple'],
    'modern slash syntax' => ['rgb(170 187 204 / 50%)'],
    'oklch' => ['oklch(0.7 0.15 30)'],
    'color-mix' => ['color-mix(in oklab, red 40%, blue)'],
    'a custom property' => ['var(--brand)'],
    'transparent' => ['transparent'],
]);

it('accepts color as a control type', function () {
    expect(OverlayControl::TYPES)->toContain('color');
});

it('creates a color control and stores the value verbatim', function () {
    $response = $this->postJson("/templates/{$this->template->id}/controls", [
        'key' => 'accent',
        'label' => 'Accent',
        'type' => 'color',
        'value' => '#7c3aed',
    ]);

    $response->assertCreated();

    $control = OverlayControl::where('key', 'accent')->firstOrFail();
    expect($control->type)->toBe('color')
        ->and($control->value)->toBe('#7c3aed');
});

/**
 * The load-bearing one. Anything CSS understands has to survive the round trip
 * untouched, because CSS already has the right failure mode for a string it
 * cannot read: it drops the declaration and the template's fallback stands.
 * If this ever starts failing, something has added validation.
 */
it('stores any CSS color without validating it', function (string $color) {
    $this->postJson("/templates/{$this->template->id}/controls", [
        'key' => 'accent',
        'type' => 'color',
        'value' => $color,
    ])->assertCreated();

    expect(OverlayControl::where('key', 'accent')->firstOrFail()->value)->toBe($color);
})->with('css colors the picker cannot parse');

it('keeps a CSS color intact when set at stream time', function (string $color) {
    $control = OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => $this->template->id,
        'key' => 'accent',
        'type' => 'color',
        'value' => '#000000',
        'source' => null,
        'source_managed' => false,
    ]);

    $this->postJson("/templates/{$this->template->id}/controls/{$control->id}/value", [
        'value' => $color,
    ])->assertOk();

    expect($control->fresh()->value)->toBe($color);
})->with('css colors the picker cannot parse');

it('sanitises a color value exactly like text', function () {
    expect(OverlayControl::sanitizeValue('color', '<b>#fff</b>'))
        ->toBe(OverlayControl::sanitizeValue('text', '<b>#fff</b>'));
});

it('renders a color control through resolveDisplayValue', function () {
    $control = OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => $this->template->id,
        'key' => 'accent',
        'type' => 'color',
        'value' => 'oklch(0.7 0.15 30)',
    ]);

    expect($control->resolveDisplayValue())->toBe('oklch(0.7 0.15 30)');
});
