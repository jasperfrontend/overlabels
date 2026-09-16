<?php

use App\Http\Middleware\RestrictOverlayHost;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// The hosted overlay (the OBS browser-source URL) answers on a second origin,
// overlabels.net, so Chrome's per-origin zoom on the dashboard stops zooming
// an overlay tab next to it. That origin must serve the overlay page and its
// API and NOTHING else: the public share pages are indexed by search engines
// under overlabels.com and must stay the only copy. And .com keeps serving
// every existing OBS URL exactly as before - .net is additive.

const OVERLAY_HOST = 'https://overlabels.net';
const APP_HOST = 'https://overlabels.com';

function overlayHostTemplate(bool $public = true): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => User::factory()->create()->id,
        'fork_of_id' => null,
        'type' => 'static',
        'is_public' => $public,
        'slug' => 'ovl-'.fake()->unique()->lexify('????????'),
    ]);
}

beforeEach(function () {
    config(['app.overlay_url' => OVERLAY_HOST]);
});

test('the overlay host serves the hosted overlay page', function () {
    $template = overlayHostTemplate();

    $this->get(OVERLAY_HOST.'/overlay/'.$template->slug)
        ->assertOk()
        ->assertSee('Overlabels :: Loading');
});

test('the overlay host serves the hosted overlay page with the trailing slash the OBS dialog mints', function () {
    // AddToObsButton builds `/overlay/{slug}/` and TokenUrlDialog appends
    // `#token`, so the URL in every OBS browser source has a trailing slash.
    $template = overlayHostTemplate();

    $this->get(OVERLAY_HOST.'/overlay/'.$template->slug.'/')
        ->assertOk()
        ->assertSee('Overlabels :: Loading');
});

test('the overlay host serves the overlay API', function () {
    // Not 404: the API is reachable there. What it answers to an empty POST
    // (a validation or auth failure) is the endpoint's business.
    $response = $this->postJson(OVERLAY_HOST.'/api/overlay/render', []);

    expect($response->status())->not->toBe(404);
});

test('the overlay host 404s the public share surface', function (string $suffix) {
    $template = overlayHostTemplate();

    $this->get(OVERLAY_HOST.'/overlay/'.$template->slug.$suffix)->assertNotFound();

    // Same template, same path, on the app host: still public.
    expect($this->get(APP_HOST.'/overlay/'.$template->slug.$suffix)->status())->not->toBe(404);
})->with([
    'preview page' => '/public',
    'markdown twin' => '/public.md',
]);

test('the overlay host 404s the public screenshot', function () {
    // No app-host counterpart here: a template without a screenshot 404s
    // there too, from the controller, which would prove nothing about the gate.
    $template = overlayHostTemplate();

    $this->get(OVERLAY_HOST.'/overlay/'.$template->slug.'/public/screenshot')->assertNotFound();
});

test('the overlay host 404s every other web route', function (string $path) {
    $this->get(OVERLAY_HOST.$path)->assertNotFound();

    expect($this->get(APP_HOST.$path)->status())->not->toBe(404);
})->with([
    'homepage' => '/',
    'login' => '/login',
    'help' => '/help',
    'products' => '/products',
]);

test('the overlay host 404s every other API route', function () {
    $this->getJson(OVERLAY_HOST.'/api/events')->assertNotFound();

    expect($this->getJson(APP_HOST.'/api/events')->status())->not->toBe(404);
});

test('the app host is untouched by the gate', function () {
    $template = overlayHostTemplate();

    $this->get(APP_HOST.'/overlay/'.$template->slug)->assertOk();
    $this->get(APP_HOST.'/overlay/'.$template->slug.'/public')->assertOk();
});

test('the gate is inert when no overlay URL is configured', function () {
    config(['app.overlay_url' => null]);

    $template = overlayHostTemplate();

    $this->get(OVERLAY_HOST.'/')->assertOk();
    $this->get(OVERLAY_HOST.'/overlay/'.$template->slug.'/public')->assertOk();
});

test('the host comparison ignores case', function () {
    $this->get('https://OverLabels.NET/login')->assertNotFound();
});

test('the allowlist is exactly the overlay page and the overlay API', function () {
    expect(RestrictOverlayHost::ALLOWED_ROUTES)->toBe(['overlay.authenticated', 'api.overlay.*']);

    expect(RestrictOverlayHost::isAllowed('overlay.authenticated'))->toBeTrue()
        ->and(RestrictOverlayHost::isAllowed('api.overlay.render'))->toBeTrue()
        ->and(RestrictOverlayHost::isAllowed('api.overlay.broadcasting.auth'))->toBeTrue()
        ->and(RestrictOverlayHost::isAllowed('overlay.public'))->toBeFalse()
        ->and(RestrictOverlayHost::isAllowed('overlay.public.markdown'))->toBeFalse()
        ->and(RestrictOverlayHost::isAllowed('overlay.public.screenshot'))->toBeFalse()
        ->and(RestrictOverlayHost::isAllowed('api.events.index'))->toBeFalse();
});

test('the Inertia share hands the frontend the overlay origin without a trailing slash', function () {
    config(['app.overlay_url' => 'https://overlabels.net/']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.index'))
        ->assertInertia(fn ($page) => $page->where('overlayOrigin', 'https://overlabels.net'));
});

test('the Inertia share is null when no overlay URL is configured', function () {
    config(['app.overlay_url' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.index'))
        ->assertInertia(fn ($page) => $page->where('overlayOrigin', null));
});
