<?php

use App\Models\EventTemplateMapping;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\OverlayShareService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The `.md` twin of a public overlay exists so one URL is enough for a language
 * model to understand a whole overlay. That only holds if the document is
 * COMPLETE (controls and requirements, not just source) and SAFE (never a
 * value belonging to somebody's connected account).
 *
 * Those two properties pull in opposite directions, which is what these tests
 * pin. The privacy tests in particular were each verified to fail when the
 * source/service split in OverlayShareService::controls() is widened.
 */

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function shareOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function shareTemplate(array $attrs = [], ?User $owner = null): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => ($owner ?? shareOwner())->id,
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

function shareMarkdown(OverlayTemplate $template): string
{
    return app(OverlayShareService::class)->markdown(
        $template,
        route('overlay.public', $template->slug).'.md',
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// The endpoint
// ──────────────────────────────────────────────────────────────────────────────

it('serves a public overlay as markdown', function () {
    $template = shareTemplate(['html' => '<div>[[[channel_followers]]]</div>']);

    $response = $this->get("/overlay/{$template->slug}/public.md");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
    expect($response->getContent())->toContain($template->name);
});

it('404s the markdown for a private overlay, like the preview page', function () {
    $template = shareTemplate(['is_public' => false]);

    $this->get("/overlay/{$template->slug}/public.md")->assertNotFound();
});

it('404s the markdown for an unknown slug', function () {
    $this->get('/overlay/no-such-overlay/public.md')->assertNotFound();
});

it('does not record a view for the markdown twin', function () {
    // view_count is a human-interest number on the preview page. Crawlers and
    // model fetches hitting the .md would drown that signal.
    $template = shareTemplate(['view_count' => 7]);

    $this->get("/overlay/{$template->slug}/public.md")->assertOk();

    expect($template->fresh()->view_count)->toBe(7);
});

// ──────────────────────────────────────────────────────────────────────────────
// Completeness: the source
// ──────────────────────────────────────────────────────────────────────────────

it('includes head, html and css verbatim', function () {
    $template = shareTemplate([
        'head' => '<link rel="stylesheet" href="https://example.test/f.css">',
        'html' => '<div class="goal">[[[channel_followers]]]</div>',
        'css' => '.goal { color: red; }',
    ]);

    expect(shareMarkdown($template))
        ->toContain('<link rel="stylesheet" href="https://example.test/f.css">')
        ->toContain('<div class="goal">[[[channel_followers]]]</div>')
        ->toContain('.goal { color: red; }');
});

it('survives a markdown fence inside the template source', function () {
    // Template HTML is arbitrary text. A fixed ``` fence would end the code
    // block early and truncate everything after it in the document.
    $template = shareTemplate([
        'html' => '<!-- ```html is a thing people write --><div>hi</div>',
    ]);

    $markdown = shareMarkdown($template);

    expect($markdown)
        ->toContain('<div>hi</div>')
        // The section after the source must still be reachable.
        ->toContain('## Controls');
});

it('quotes a name that would otherwise break the yaml front matter', function () {
    $template = shareTemplate(['name' => 'Goal: the big one']);

    expect(shareMarkdown($template))->toContain('name: "Goal: the big one"');
});

// ──────────────────────────────────────────────────────────────────────────────
// Completeness: controls
// ──────────────────────────────────────────────────────────────────────────────

it('describes the controls the overlay defines, with their defaults', function () {
    $template = shareTemplate(['html' => '<div>[[[c:goal_target]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'goal_target',
        'label' => 'Goal target',
        'type' => 'number',
        'value' => '1000',
        'source' => null,
    ]);

    expect(shareMarkdown($template))
        ->toContain('[[[c:goal_target]]]')
        ->toContain('Goal target')
        ->toContain('1000');
});

it('marks a defined control that the source never references', function () {
    $template = shareTemplate(['html' => '<div>nothing here</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'leftover',
        'type' => 'text',
        'value' => 'x',
        'source' => null,
    ]);

    $document = app(OverlayShareService::class)->document($template);

    expect($document['controls'][0]['used'])->toBeFalse();
});

it('counts the _at companion as a reference to its control', function () {
    // Every control gets a synthesised `<key>_at` timestamp at render time, so
    // a template using only the companion is still using the control.
    $template = shareTemplate(['html' => '<div>[[[c:last_raid_at]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'last_raid',
        'type' => 'text',
        'value' => '',
        'source' => null,
    ]);

    $document = app(OverlayShareService::class)->document($template);

    expect($document['controls'][0]['used'])->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// Safety: no account data leaves through the document
// ──────────────────────────────────────────────────────────────────────────────

it('never ships the value of a service-managed control', function () {
    // The load-bearing privacy test. latest_donor_name holds a real person's
    // name and total_received is revenue; both are live account data that
    // happens to sit in a row attached to this template.
    $template = shareTemplate(['html' => '<div>[[[c:kofi:latest_donor_name]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'latest_donor_name',
        'label' => 'Latest Donor Name',
        'type' => 'text',
        'value' => 'Wilhelmina Featherstonehaugh',
        'source' => 'kofi',
        'source_managed' => true,
    ]);

    $markdown = shareMarkdown($template);
    $document = app(OverlayShareService::class)->document($template);

    expect($markdown)->not->toContain('Wilhelmina Featherstonehaugh');
    // It is still described - just from the driver definition, not the row.
    expect($markdown)->toContain('[[[c:kofi:latest_donor_name]]]');
    expect($document['controls'])->toBeEmpty();
});

it('shows the formula of an expression control', function () {
    // An expression control keeps its formula in config.expression, not in
    // value, so a document that prints only values renders a maths-driven
    // overlay as a table of blanks - complete-looking and useless.
    $template = shareTemplate(['html' => '<div>[[[c:orbit_x]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'orbit_x',
        'type' => 'expression',
        'value' => '',
        'config' => ['expression' => 'sin(now() / 1000) * 50 + 50', 'dependencies' => []],
        'source' => null,
    ]);

    expect(shareMarkdown($template))->toContain('sin(now() / 1000) * 50 + 50');
});

it('strips runtime state out of a control config', function () {
    // A timer's started_at is when the owner last started their timer, not a
    // property of the overlay.
    $template = shareTemplate(['html' => '<div>[[[c:countdown]]]</div>']);

    OverlayControl::factory()->create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'countdown',
        'type' => 'timer',
        'value' => '',
        'config' => ['direction' => 'down', 'started_at' => 1771771091],
        'source' => null,
    ]);

    $document = app(OverlayShareService::class)->document($template);

    expect($document['controls'][0]['config'])
        ->toHaveKey('direction')
        ->not->toHaveKey('started_at');
});

// ──────────────────────────────────────────────────────────────────────────────
// Requirements
// ──────────────────────────────────────────────────────────────────────────────

it('reports the integrations a template needs, from its tags alone', function () {
    // No integration rows and no provisioned controls exist here: the
    // requirement is derived from the source, which is the only thing a
    // reader who does not own the overlay can act on.
    $template = shareTemplate([
        'html' => '<div>[[[c:kofi:donations_received]]] [[[c:streamlabs:total_received]]]</div>',
    ]);

    $document = app(OverlayShareService::class)->document($template);

    expect(array_column($document['services'], 'service'))->toBe(['kofi', 'streamlabs']);
    expect(shareMarkdown($template))
        ->toContain('Ko-fi')
        ->toContain('Streamlabs')
        // Driver-supplied label, proving the description came from the driver.
        ->toContain('Ko-fi Donations Received');
});

it('finds service tags in head and css, not just html', function () {
    $template = shareTemplate([
        'head' => '<style>.a::after { content: "[[[c:kofi:latest_donor_name]]]"; }</style>',
        'css' => '.b { --total: [[[c:bmac:total_received]]]; }',
    ]);

    $document = app(OverlayShareService::class)->document($template);

    expect(array_column($document['services'], 'service'))->toBe(['bmac', 'kofi']);
});

it('flags a service tag the driver does not provision', function () {
    $template = shareTemplate(['html' => '<div>[[[c:kofi:not_a_real_key]]]</div>']);

    $document = app(OverlayShareService::class)->document($template);

    expect($document['services'][0]['controls'][0]['known'])->toBeFalse();
    expect(shareMarkdown($template))->toContain('likely a typo');
});

it('treats c:list: as a list dependency, not an integration', function () {
    $template = shareTemplate(['html' => '<div>[[[c:list:song_requests]]]</div>']);

    $document = app(OverlayShareService::class)->document($template);

    expect($document['services'])->toBeEmpty()
        ->and($document['lists'])->toBe([
            ['slug' => 'song_requests', 'tag' => 'c:list:song_requests'],
        ]);
});

it('separates live data tags from control tags', function () {
    $template = shareTemplate([
        'html' => '<div>[[[channel_followers]]] [[[c:goal_target]]] [[[user_name]]]</div>',
    ]);

    $document = app(OverlayShareService::class)->document($template);

    expect($document['dataTags'])->toBe(['channel_followers', 'user_name']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Alerts
// ──────────────────────────────────────────────────────────────────────────────

it('describes alert behaviour and the author triggers', function () {
    $owner = shareOwner();
    $template = shareTemplate([
        'type' => 'alert',
        'html' => '<div>[[[event.user_name]]] cheered [[[event.bits]]]</div>',
        'tts_message' => 'Thanks [[[event.user_name]]]',
        'tts_delay_ms' => 500,
    ], $owner);

    EventTemplateMapping::create([
        'user_id' => $owner->id,
        'template_id' => $template->id,
        'event_type' => 'channel.cheer',
        'condition_type' => EventTemplateMapping::CONDITION_AT_LEAST,
        'condition_value' => 100,
        'duration_ms' => 7000,
        'enabled' => true,
    ]);

    $markdown = shareMarkdown($template);

    expect($markdown)
        ->toContain('## Alert behaviour')
        ->toContain('Thanks [[[event.user_name]]]')
        ->toContain('500ms')
        ->toContain('at least 100')
        ->toContain('7000ms')
        // The honesty clause: triggers describe the author's wiring and do not
        // come with a copy. Dropping this line would misrepresent what Copy does.
        ->toContain('not** copied');
});

it('omits the alert section for a static overlay', function () {
    $template = shareTemplate(['type' => 'static', 'html' => '<div>hi</div>']);

    expect(shareMarkdown($template))->not->toContain('## Alert behaviour');
    expect(app(OverlayShareService::class)->document($template)['alert'])->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// The two surfaces agree
// ──────────────────────────────────────────────────────────────────────────────

it('ships the same document to the preview page', function () {
    // The page and the .md render from one OverlayShareService::document call,
    // so they cannot disagree about what an overlay requires. If the prop is
    // dropped, the page silently loses its controls panel while the .md keeps
    // its section - the exact drift this arrangement exists to prevent.
    $template = shareTemplate(['html' => '<div>[[[c:kofi:donations_received]]]</div>']);

    $this->get("/overlay/{$template->slug}/public")
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('overlay/public-preview')
                ->where('share.services.0.service', 'kofi')
                ->where('markdownUrl', route('overlay.public', $template->slug).'.md')
        );
});

it('advertises the markdown twin from the preview page head', function () {
    $template = shareTemplate();

    expect($this->get("/overlay/{$template->slug}/public")->getContent())
        ->toContain('rel="alternate" type="text/markdown"')
        ->toContain("/overlay/{$template->slug}/public.md");
});

it('does not advertise a markdown twin on unrelated pages', function () {
    // The <link> is shared per-route, not global. A leak would claim every page
    // has a markdown form, which is false and would send crawlers to 404s.
    expect($this->get('/')->getContent())
        ->not->toContain('type="text/markdown"');
});
