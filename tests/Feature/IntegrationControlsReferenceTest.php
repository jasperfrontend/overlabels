<?php

use App\Console\Commands\BuildIntegrationControlsReference;
use App\Services\External\ExternalServiceRegistry;
use App\Services\HelpReferenceService;

/**
 * The integration-controls reference category is generated from the drivers,
 * not hand-written. Before it existed the vault documented 3 of 7 services and
 * omitted Ko-fi entirely, which is what hand-maintained documentation of a
 * moving target always converges on.
 *
 * The load-bearing test is the drift check: the generated files are committed,
 * so a driver change that is not regenerated ships stale docs. `--check` is the
 * same code path the command uses to write, so it cannot pass while the output
 * would differ.
 */
it('has a committed page for every registered service', function () {
    $service = app(HelpReferenceService::class);
    $slugs = collect($service->all())
        ->where('category', BuildIntegrationControlsReference::CATEGORY)
        ->pluck('slug')
        ->all();

    foreach (ExternalServiceRegistry::services() as $key) {
        expect($slugs)->toContain($key);
    }

    expect($slugs)->toContain('all-integration-controls')
        ->and($slugs)->toHaveCount(count(ExternalServiceRegistry::services()) + 1);
});

it('fails when a driver changes and the pages are not regenerated', function () {
    // The whole point of committing generated output. If this ever passes while
    // the files are stale, the generator and the checker have diverged.
    $this->artisan('help:build-integration-controls --check')->assertSuccessful();
});

it('documents the real control keys, not remembered ones', function () {
    $entry = app(HelpReferenceService::class)->get(BuildIntegrationControlsReference::CATEGORY, 'kofi');

    expect($entry)->not->toBeNull();

    foreach (ExternalServiceRegistry::driver('kofi')->getAutoProvisionedControls() as $control) {
        expect($entry['body'])->toContain("[[[c:kofi:{$control['key']}]]]");
    }
});

it('separates the shared donation schema from what a service adds', function () {
    // Throne carries the six shared keys plus three of its own. Flattening them
    // into one table is what makes people think `latest_item_name` is portable.
    $throne = app(HelpReferenceService::class)->get(BuildIntegrationControlsReference::CATEGORY, 'throne');

    expect($throne['body'])
        ->toContain('## The shared donation schema')
        ->toContain('## Specific to Throne')
        ->toContain('[[[c:throne:latest_item_name]]]');

    // GPS shares none of them, so claiming it "extends" the schema would be a lie.
    $gps = app(HelpReferenceService::class)->get(BuildIntegrationControlsReference::CATEGORY, 'gps');

    expect($gps['body'])->not->toContain('## The shared donation schema');
});

it('serves every generated page as crawlable html', function () {
    foreach ([...ExternalServiceRegistry::services(), 'all-integration-controls'] as $slug) {
        $this->get('/help/reference/'.BuildIntegrationControlsReference::CATEGORY."/{$slug}")
            ->assertOk();
    }
});

it('lists the generated pages in the sitemap', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->toContain('/help/reference/integration-controls/kofi<')
        ->toContain('/help/reference/integration-controls/bmac<')
        ->toContain('/help/reference/integration-controls/all-integration-controls<');
});

it('301s the retired hand-written control pages to their generated replacements', function () {
    // These URLs were in the sitemap and the reference is the best-indexed part
    // of the site, so removing the markdown without a redirect would strand
    // whatever index equity they had accumulated.
    $retired = [
        'ko-fi-auto-provisioned-controls' => 'kofi',
        'streamlabs-auto-provisioned-controls' => 'streamlabs',
        'streamelements-auto-provisioned-controls' => 'streamelements',
        'fourthwall-auto-provisioned-controls' => 'fourthwall',
    ];

    foreach ($retired as $legacySlug => $service) {
        $this->get("/help/reference/eventsub-tags/{$legacySlug}")
            ->assertRedirect("/help/reference/integration-controls/{$service}");
    }
});

it('no longer carries the stale "all four integrations" claim', function () {
    // The retired pages said the shared schema spanned four services. Seven
    // drivers provision `donations_received`, so that was wrong before it was
    // deleted, and it is the kind of wrong that hand-maintenance reintroduces.
    $bodies = collect(app(HelpReferenceService::class)->all())->pluck('body')->implode("\n");

    expect($bodies)->not->toContain('identical across all four integrations');
});

it('keeps the category registered in php and typescript', function () {
    $ts = (string) file_get_contents(resource_path('js/composables/useHelpReference.ts'));

    expect(HelpReferenceService::CATEGORY_LABELS['integration-controls'])->toBe('Integration Controls')
        ->and(HelpReferenceService::CATEGORY_ORDER)->toContain('integration-controls')
        ->and($ts)->toContain("'integration-controls': 'Integration Controls'");
});

it('resolves the index page wikilinks to real service pages', function () {
    $html = $this->get('/help/reference/integration-controls/all-integration-controls')->getContent();

    // A wikilink to an unknown slug degrades to inline code rather than erroring,
    // so a broken link here is silent without this assertion.
    foreach (ExternalServiceRegistry::services() as $key) {
        expect($html)->toContain("/help/reference/integration-controls/{$key}");
    }
});
