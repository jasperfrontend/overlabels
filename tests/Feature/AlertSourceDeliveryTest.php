<?php

use App\Events\AlertTriggered;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/*
 * Reverb refuses an events request over 10 000 bytes, and an alert used to
 * carry its whole html + css on every broadcast - so an alert bigger than
 * that could be saved and never fire. The overlay now loads alert sources at
 * mount (`alert_sources`), the broadcast carries a slug + version and inlines
 * the source only while it fits, and api.overlay.alert-source serves a copy
 * the overlay is missing.
 */

beforeEach(function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function alertSourceUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);
}

function alertSourceToken(User $user): string
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'alert-source-test',
        'is_active' => true,
    ]);

    return $plain;
}

function alertSourceTemplate(User $user, string $type, array $attrs = []): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => $type,
        'head' => '',
        'html' => '<div>[[[event.from_name]]]</div>',
        'css' => 'div { color: red; }',
        'slug' => $type.'-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ], $attrs));
}

/** Roughly the request Reverb measures: the payload JSON-encoded inside a JSON envelope. */
function alertSourceWrappedBytes(AlertTriggered $event): int
{
    return strlen(json_encode([
        'name' => $event->broadcastAs(),
        'channels' => ['private-alerts.'.$event->broadcasterId],
        'data' => json_encode($event->broadcastWith()),
    ]));
}

function alertSourceEvent(string $html, string $css = '', ?string $slug = 'some-alert', string $head = ''): AlertTriggered
{
    return new AlertTriggered(
        alertId: 'alert-1',
        html: $html,
        css: $css,
        data: ['event.from_name' => 'Jules'],
        duration: 8000,
        broadcasterId: '12345',
        alertTemplateSlug: $slug,
        head: $head,
    );
}

test('a small alert rides the broadcast whole, with its version', function () {
    $event = alertSourceEvent('<b>hi</b>', '.b{}', head: '<link rel="stylesheet" href="https://f.example/a.css">');
    $alert = $event->broadcastWith()['alert'];

    expect($alert['html'])->toBe('<b>hi</b>')
        ->and($alert['source_omitted'])->toBeFalse()
        ->and($alert['css'])->toBe('.b{}')
        ->and($alert['head'])->toBe('<link rel="stylesheet" href="https://f.example/a.css">')
        ->and($alert['alert_template_slug'])->toBe('some-alert')
        ->and($alert['alert_template_version'])->toBe(OverlayTemplate::sourceVersion('<link rel="stylesheet" href="https://f.example/a.css">', '<b>hi</b>', '.b{}'));
});

test('an alert too big for Reverb leaves its source behind and still fits under 10 KB', function () {
    $css = str_repeat('.stp-card { border: 3px solid var(--accent); }'."\n", 400); // ~20 KB
    $event = alertSourceEvent('<div class="stp-card"></div>', $css);
    $alert = $event->broadcastWith()['alert'];

    // Empty strings rather than nulls: the renderer from before alert_sources
    // runs html through processTemplate() and would throw on null.
    expect($alert['source_omitted'])->toBeTrue()
        ->and($alert['html'])->toBe('')
        ->and($alert['css'])->toBe('')
        ->and($alert['head'])->toBe('')
        ->and($alert['alert_template_slug'])->toBe('some-alert')
        ->and($alert['alert_template_version'])->toBe(OverlayTemplate::sourceVersion('', '<div class="stp-card"></div>', $css))
        ->and($alert['data'])->toBe(['event.from_name' => 'Jules'])
        ->and(alertSourceWrappedBytes($event))->toBeLessThan(10_000);
});

test('without a slug there is nothing to look the source up by, so it always rides', function () {
    $css = str_repeat('x', 20_000);
    expect(alertSourceEvent('<b></b>', $css, slug: null)->broadcastWith()['alert']['css'])->toBe($css);
});

test('the version moves with the source and ignores everything else', function () {
    $user = alertSourceUser();
    $alert = alertSourceTemplate($user, 'alert');
    $before = $alert->alertSource()['version'];

    $alert->update(['name' => 'Renamed']);
    expect($alert->fresh()->alertSource()['version'])->toBe($before);

    $alert->update(['css' => 'div { color: blue; }']);
    expect($alert->fresh()->alertSource()['version'])->not->toBe($before);
});

test('the overlay payload carries the source of every alert that can fire on it', function () {
    $user = alertSourceUser();
    $static = alertSourceTemplate($user, 'static', ['html' => '<div></div>']);
    $elsewhere = alertSourceTemplate($user, 'static', ['html' => '<div></div>']);

    $everywhere = alertSourceTemplate($user, 'alert', ['head' => '<link rel="stylesheet" href="https://f.example/a.css">']);
    $targetedHere = alertSourceTemplate($user, 'alert');
    $targetedHere->targetStaticOverlays()->attach($static->id);
    $targetedElsewhere = alertSourceTemplate($user, 'alert');
    $targetedElsewhere->targetStaticOverlays()->attach($elsewhere->id);
    $someoneElses = alertSourceTemplate(alertSourceUser(), 'alert');

    $sources = $this->postJson('/api/overlay/render', ['slug' => $static->slug, 'token' => alertSourceToken($user)])
        ->assertOk()
        ->json('alert_sources');

    expect(array_keys($sources))->toEqualCanonicalizing([$everywhere->slug, $targetedHere->slug])
        ->and($sources[$everywhere->slug])->toBe($everywhere->alertSource())
        ->and($sources)->not->toHaveKey($targetedElsewhere->slug)
        ->and($sources)->not->toHaveKey($someoneElses->slug);
});

test('an overlay with no alerts gets an empty object, not an empty list', function () {
    $user = alertSourceUser();
    $static = alertSourceTemplate($user, 'static', ['html' => '<div></div>']);

    $response = $this->postJson('/api/overlay/render', ['slug' => $static->slug, 'token' => alertSourceToken($user)]);

    expect($response->getContent())->toContain('"alert_sources":{}');
});

test('an overlay can fetch the source of its owner\'s alert', function () {
    $user = alertSourceUser();
    $alert = alertSourceTemplate($user, 'alert', ['compiled_css' => '.p-4{padding:1rem}']);

    $this->postJson('/api/overlay/alert-source', ['slug' => $alert->slug, 'token' => alertSourceToken($user)])
        ->assertOk()
        ->assertExactJson(['slug' => $alert->slug, ...$alert->alertSource(), 'compiled_css' => '.p-4{padding:1rem}']);
});

test('the alert source endpoint reads and never writes', function () {
    $user = alertSourceUser();
    $alert = alertSourceTemplate($user, 'alert');
    $plain = alertSourceToken($user);
    $token = OverlayAccessToken::where('user_id', $user->id)->firstOrFail();

    $this->postJson('/api/overlay/alert-source', ['slug' => $alert->slug, 'token' => $plain])->assertOk();

    expect($token->fresh()->access_count)->toBe($token->access_count)
        ->and($token->fresh()->last_used_at)->toEqual($token->last_used_at);
});

test('the alert source endpoint refuses a bad token', function () {
    $alert = alertSourceTemplate(alertSourceUser(), 'alert');

    $this->postJson('/api/overlay/alert-source', ['slug' => $alert->slug, 'token' => str_repeat('a', 64)])->assertUnauthorized();
});

test('a token cannot read another user\'s alert, or a template that is not an alert', function () {
    $user = alertSourceUser();
    $plain = alertSourceToken($user);
    $theirs = alertSourceTemplate(alertSourceUser(), 'alert');
    $ownStatic = alertSourceTemplate($user, 'static');

    $this->postJson('/api/overlay/alert-source', ['slug' => $theirs->slug, 'token' => $plain])->assertNotFound();
    $this->postJson('/api/overlay/alert-source', ['slug' => $ownStatic->slug, 'token' => $plain])->assertNotFound();
    $this->postJson('/api/overlay/alert-source', ['slug' => 'no-such-slug', 'token' => $plain])->assertNotFound();
});

test('a Ko-fi tip fires an alert far bigger than Reverb would carry', function () {
    Event::fake([AlertTriggered::class]);
    $user = alertSourceUser();
    $css = str_repeat('.stp-amt { outline: 3px solid var(--accent); }'."\n", 400);
    $alert = alertSourceTemplate($user, 'alert', ['css' => $css, 'head' => '<link rel="stylesheet" href="https://f.example/a.css">']);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'tok'])),
    ]);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 8000,
    ]);

    $this->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode([
        'verification_token' => 'tok',
        'kofi_transaction_id' => 'txn-'.fake()->uuid(),
        'from_name' => 'Bob',
        'message' => 'Hi!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ])]);

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) use ($alert) {
        $payload = $event->broadcastWith()['alert'];

        return $payload['source_omitted'] === true
            && $payload['alert_template_slug'] === $alert->slug
            && $payload['alert_template_version'] === $alert->alertSource()['version']
            && alertSourceWrappedBytes($event) < 10_000;
    });
});
