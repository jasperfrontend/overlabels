<?php

use App\Models\Kit;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\KitShareService;
use App\Services\OverlayShareService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * `/kits/{kit}.md` is the one route in the kit namespace that lives outside the
 * auth wall, because a URL handed to a language model cannot require a login.
 *
 * That is only safe while the document contains nothing which is not already
 * world-readable. A kit's `is_public` and a template's `is_public` are separate
 * flags with nothing enforcing a relationship between them - a public kit may
 * contain private templates - so the kit flag gates the document and each
 * template's own flag gates whether its source appears inside it.
 *
 * The two tests covering that split were verified to fail when the per-template
 * check is removed.
 */

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function kitOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function kitTemplate(User $owner, array $attrs = []): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'fork_of_id' => null,
        'type' => 'static',
        'is_public' => true,
        'slug' => 'ovl-'.fake()->unique()->lexify('????????'),
        'head' => '',
        'html' => '',
        'css' => '',
        'metadata' => null,
    ], $attrs));
}

function shareKit(User $owner, array $templates, array $attrs = []): Kit
{
    $kit = Kit::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'forked_from_id' => null,
        'is_public' => true,
    ], $attrs));

    $kit->templates()->attach(collect($templates)->pluck('id')->all());

    return $kit->fresh();
}

function kitMarkdown(Kit $kit): string
{
    return app(KitShareService::class)->markdown($kit, route('kits.markdown', $kit));
}

// ──────────────────────────────────────────────────────────────────────────────
// The endpoint, and the auth asymmetry that is the whole point of it
// ──────────────────────────────────────────────────────────────────────────────

it('serves a public kit as markdown to a logged-out visitor', function () {
    // kits.show redirects to login; this route deliberately does not, because
    // a URL you hand to a language model cannot sit behind a session.
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner, ['html' => '<div>hi</div>'])]);

    $response = $this->get("/kits/{$kit->id}.md");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
    expect($response->getContent())->toContain($kit->title);
});

it('still sends a logged-out visitor to login for the kit page itself', function () {
    // Pins the asymmetry from the other side. If kits.show ever becomes public
    // this test should be deleted deliberately, not discovered by surprise.
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner)]);

    $this->get("/kits/{$kit->id}")->assertRedirect();
});

it('404s the markdown for a private kit', function () {
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner)], ['is_public' => false]);

    $this->get("/kits/{$kit->id}.md")->assertNotFound();
});

it('404s the markdown for a private kit even for its own owner', function () {
    // The route has no session to check against - that is why it sits outside
    // the auth group - so is_public is the only gate and it applies to
    // everybody. The owner reads their own kit on the page instead.
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner)], ['is_public' => false]);

    $this->actingAs($owner)->get("/kits/{$kit->id}.md")->assertNotFound();
});

it('does not let the show route swallow the .md suffix', function () {
    // `kits/{kit}` has no numeric constraint, so it would match `3.md` happily.
    // The markdown route only wins because it is registered first.
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner)]);

    expect(app('router')->getRoutes()->match(
        Request::create("/kits/{$kit->id}.md", 'GET')
    )->getName())->toBe('kits.markdown');
});

// ──────────────────────────────────────────────────────────────────────────────
// Per-template privacy inside a public kit
// ──────────────────────────────────────────────────────────────────────────────

it('withholds the source of a private template inside a public kit', function () {
    // Nothing stops a public kit containing private templates: KitController
    // validates only that they belong to the user. The template's own flag is
    // the gate on its source.
    $owner = kitOwner();
    $public = kitTemplate($owner, ['name' => 'Shared Bar', 'html' => '<div>PUBLIC_MARKUP</div>']);
    $private = kitTemplate($owner, [
        'name' => 'Secret Bar',
        'is_public' => false,
        'html' => '<div>PRIVATE_MARKUP</div>',
    ]);

    $markdown = kitMarkdown(shareKit($owner, [$public, $private]));

    expect($markdown)
        ->toContain('PUBLIC_MARKUP')
        ->not->toContain('PRIVATE_MARKUP')
        // Listed, because copying the kit copies it - hiding its existence
        // would misdescribe what you get.
        ->toContain('Secret Bar')
        ->toContain('Private.');
});

it('withholds the controls of a private template too', function () {
    $owner = kitOwner();
    $private = kitTemplate($owner, ['is_public' => false, 'html' => '<div>[[[c:secret_key]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $private->id,
        'user_id' => $owner->id,
        'key' => 'secret_key',
        'label' => 'Unreleased Thing',
        'type' => 'text',
        'value' => 'UNRELEASED_VALUE',
        'source' => null,
    ]);

    expect(kitMarkdown(shareKit($owner, [$private])))
        ->not->toContain('UNRELEASED_VALUE')
        ->not->toContain('Unreleased Thing');
});

it('says up front how many overlays are withheld', function () {
    $owner = kitOwner();
    $kit = shareKit($owner, [
        kitTemplate($owner),
        kitTemplate($owner, ['is_public' => false]),
        kitTemplate($owner, ['is_public' => false]),
    ]);

    expect(kitMarkdown($kit))->toContain('2 of the 3 overlays in this kit are private');
});

// ──────────────────────────────────────────────────────────────────────────────
// Composition
// ──────────────────────────────────────────────────────────────────────────────

it('lists every overlay in the contents table with its type', function () {
    $owner = kitOwner();
    $kit = shareKit($owner, [
        kitTemplate($owner, ['name' => 'Toolbar', 'type' => 'static']),
        kitTemplate($owner, ['name' => 'Follow Alert', 'type' => 'alert']),
    ]);

    $markdown = kitMarkdown($kit);

    expect($markdown)
        ->toContain('## Contents')
        ->toContain('| Toolbar | static | yes |')
        ->toContain('| Follow Alert | alert | yes |');
});

it('orders statics before alerts', function () {
    // Statics are what you install; alerts fire into them. Reading order
    // follows that rather than insertion order.
    $owner = kitOwner();
    $kit = shareKit($owner, [
        kitTemplate($owner, ['name' => 'Zed Alert', 'type' => 'alert']),
        kitTemplate($owner, ['name' => 'Abacus Bar', 'type' => 'static']),
    ]);

    $markdown = kitMarkdown($kit);

    expect(strpos($markdown, '1. Abacus Bar'))->toBeLessThan(strpos($markdown, '2. Zed Alert'));
});

it('aggregates the integrations every overlay needs into one kit-level list', function () {
    // "This kit needs Ko-fi connected" is a decision made once before
    // installing, not re-derived per overlay while reading.
    $owner = kitOwner();
    $kit = shareKit($owner, [
        kitTemplate($owner, ['html' => '<div>[[[c:kofi:donations_received]]]</div>']),
        kitTemplate($owner, ['html' => '<div>[[[c:streamlabs:total_received]]]</div>']),
    ]);

    expect(kitMarkdown($kit))
        ->toContain('## What this kit needs')
        ->toContain('**Ko-fi, Streamlabs**');
});

it('nests each overlay section under the kit rather than competing with it', function () {
    // The overlay body is rendered a heading level deeper inside a kit, so
    // "Source" reads as belonging to an overlay and not to the kit.
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner, ['html' => '<div>hi</div>'])]);

    $markdown = kitMarkdown($kit);

    expect($markdown)
        ->toContain("\n## Overlays\n")
        ->toContain("\n#### Source\n")
        ->not->toContain("\n## Source\n");
});

it('renders an overlay identically whether standalone or inside a kit', function () {
    // The kit document composes OverlayShareService rather than reimplementing
    // it. If the two ever describe controls differently, one of them is wrong.
    $owner = kitOwner();
    $template = kitTemplate($owner, ['html' => '<div>[[[c:goal]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $owner->id,
        'key' => 'goal',
        'label' => 'Goal target',
        'type' => 'number',
        'value' => '4242',
        'source' => null,
    ]);

    $standalone = app(OverlayShareService::class)
        ->markdown($template, route('overlay.public', $template->slug).'.md');

    expect(kitMarkdown(shareKit($owner, [$template])))
        ->toContain('4242')
        ->toContain('Goal target');
    expect($standalone)->toContain('4242');
});

it('handles an empty kit without inventing sections', function () {
    $owner = kitOwner();
    $kit = shareKit($owner, []);

    expect(kitMarkdown($kit))
        ->toContain('This kit is empty.')
        ->not->toContain('## Overlays');
});

// ──────────────────────────────────────────────────────────────────────────────
// The page
// ──────────────────────────────────────────────────────────────────────────────

it('hands the kit page a markdown url for a public kit', function () {
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner)]);

    $this->actingAs($owner)->get("/kits/{$kit->id}")
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('kits/show')
                ->where('markdownUrl', route('kits.markdown', $kit))
        );
});

it('hands the kit page no markdown url for a private kit', function () {
    // The affordance would otherwise link its own owner to a 404.
    $owner = kitOwner();
    $kit = shareKit($owner, [kitTemplate($owner)], ['is_public' => false]);

    $this->actingAs($owner)->get("/kits/{$kit->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('markdownUrl', null));
});
