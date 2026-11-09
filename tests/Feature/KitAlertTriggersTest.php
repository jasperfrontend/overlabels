<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * A kit declares which event fires each of its alerts. The kit page plays
 * them with sample events before anyone installs anything, and copying the
 * kit points the copier's events at the copied alerts, never over an alert
 * they already have.
 */

function kitTrigUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function kitTrigTemplate(User $owner, string $type, array $attrs = []): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'fork_of_id' => null,
        'type' => $type,
        'is_public' => false,
        'head' => '',
        'html' => '<div class="a">[[[event.from_name]]]</div>',
        'css' => '.a{color:red}',
        'metadata' => null,
    ], $attrs));
}

/** @return array{0:User,1:Kit,2:OverlayTemplate,3:OverlayTemplate,4:OverlayTemplate} */
function kitTrigKit(?array $triggers = null): array
{
    $author = kitTrigUser();
    $kofi = kitTrigTemplate($author, 'alert', ['name' => 'Ko-fi alert']);
    $follow = kitTrigTemplate($author, 'alert', ['name' => 'Follow alert']);
    $scene = kitTrigTemplate($author, 'static', ['name' => 'Scene', 'html' => '<main>[[[channel_name]]]</main>']);

    $kit = Kit::factory()->create(['owner_id' => $author->id, 'is_public' => true, 'alert_triggers' => $triggers ?? [
        ['template_id' => $kofi->id, 'service' => 'kofi', 'event_type' => 'donation', 'duration_ms' => 8000],
        ['template_id' => $kofi->id, 'service' => 'kofi', 'event_type' => 'subscription', 'duration_ms' => 8000],
        ['template_id' => $follow->id, 'service' => 'twitch', 'event_type' => 'channel.follow', 'duration_ms' => 6000],
    ]]);
    $kit->templates()->attach([$kofi->id, $follow->id, $scene->id]);

    return [$author, $kit, $kofi, $follow, $scene];
}

function kitTrigPayload(array $templateIds, array $triggers): array
{
    return ['title' => 'Kit', 'description' => null, 'is_public' => true, 'template_ids' => $templateIds, 'alert_triggers' => $triggers];
}

test('a kit saves the triggers its author declares', function () {
    $user = kitTrigUser();
    $alert = kitTrigTemplate($user, 'alert');

    $this->actingAs($user)->post('/kits', kitTrigPayload([$alert->id], [
        ['template_id' => $alert->id, 'service' => 'kofi', 'event_type' => 'donation', 'duration_ms' => 8000],
    ]))->assertRedirect();

    expect(Kit::where('owner_id', $user->id)->first()->alert_triggers)
        ->toBe([['template_id' => $alert->id, 'service' => 'kofi', 'event_type' => 'donation', 'duration_ms' => 8000]]);
});

test('a trigger must point at an alert in the kit, name a real event, and claim it once', function (string $case) {
    $user = kitTrigUser();
    $alert = kitTrigTemplate($user, 'alert');
    $static = kitTrigTemplate($user, 'static');
    $outside = kitTrigTemplate($user, 'alert');

    $trigger = match ($case) {
        'static' => [['template_id' => $static->id, 'service' => 'kofi', 'event_type' => 'donation']],
        'outside' => [['template_id' => $outside->id, 'service' => 'kofi', 'event_type' => 'donation']],
        'unknown' => [['template_id' => $alert->id, 'service' => 'kofi', 'event_type' => 'refund']],
        'twice' => [
            ['template_id' => $alert->id, 'service' => 'kofi', 'event_type' => 'donation'],
            ['template_id' => $alert->id, 'service' => 'kofi', 'event_type' => 'donation'],
        ],
    };

    $this->actingAs($user)->post('/kits', kitTrigPayload([$alert->id, $static->id], $trigger))
        ->assertSessionHasErrors();
    expect(Kit::where('owner_id', $user->id)->count())->toBe(0);
})->with(['static', 'outside', 'unknown', 'twice']);

test('an edit that does not send triggers leaves them alone, and one that does replaces them', function () {
    [$author, $kit, $kofi] = kitTrigKit();
    $ids = $kit->templates->pluck('id')->all();
    $before = $kit->alert_triggers;

    $this->actingAs($author)->put("/kits/{$kit->id}", ['title' => 'Kit', 'is_public' => true, 'template_ids' => $ids])->assertRedirect();
    expect($kit->fresh()->alert_triggers)->toBe($before);

    $this->actingAs($author)->put("/kits/{$kit->id}", kitTrigPayload($ids, [
        ['template_id' => $kofi->id, 'service' => 'streamlabs', 'event_type' => 'donation', 'duration_ms' => 4000],
    ]))->assertRedirect();
    expect($kit->fresh()->alert_triggers)->toBe([['template_id' => $kofi->id, 'service' => 'streamlabs', 'event_type' => 'donation', 'duration_ms' => 4000]]);
});

test('copying a kit wires its events to the copied alerts', function () {
    [, $kit, $kofi, $follow] = kitTrigKit();
    $copier = kitTrigUser();

    $this->actingAs($copier)->post("/kits/{$kit->id}/fork")->assertRedirect()
        ->assertSessionHas('success', 'Kit copied! 3 events now fire their alerts.');

    $copy = Kit::where('owner_id', $copier->id)->firstOrFail();
    $copiedKofi = $copy->templates->firstWhere('fork_of_id', $kofi->id);
    $copiedFollow = $copy->templates->firstWhere('fork_of_id', $follow->id);

    expect(ExternalEventTemplateMapping::where('user_id', $copier->id)->orderBy('event_type')->get(['event_type', 'overlay_template_id', 'duration_ms', 'enabled'])->toArray())
        ->toBe([
            ['event_type' => 'donation', 'overlay_template_id' => $copiedKofi->id, 'duration_ms' => 8000, 'enabled' => true],
            ['event_type' => 'subscription', 'overlay_template_id' => $copiedKofi->id, 'duration_ms' => 8000, 'enabled' => true],
        ])
        ->and(EventTemplateMapping::where('user_id', $copier->id)->where('event_type', 'channel.follow')->value('template_id'))->toBe($copiedFollow->id)
        // The copy's own triggers point at the copies, so a copy of a copy still works.
        ->and(collect($copy->alert_triggers)->pluck('template_id')->unique()->sort()->values()->all())
        ->toBe(collect([$copiedKofi->id, $copiedFollow->id])->sort()->values()->all());
});

test('copying a kit never takes over an event the copier already has an alert for', function () {
    [, $kit] = kitTrigKit();
    $copier = kitTrigUser();
    $theirs = kitTrigTemplate($copier, 'alert', ['name' => 'My tip alert']);
    $mine = ExternalEventTemplateMapping::create([
        'user_id' => $copier->id, 'service' => 'kofi', 'event_type' => 'donation',
        'overlay_template_id' => $theirs->id, 'enabled' => false, 'duration_ms' => 3000,
    ]);

    $this->actingAs($copier)->post("/kits/{$kit->id}/fork")
        ->assertSessionHas('success', 'Kit copied! 2 events now fire their alerts. 1 left alone because you already have an alert for it.');

    expect($mine->fresh()->only(['overlay_template_id', 'enabled', 'duration_ms']))
        ->toBe(['overlay_template_id' => $theirs->id, 'enabled' => false, 'duration_ms' => 3000])
        ->and(ExternalEventTemplateMapping::where('user_id', $copier->id)->where('event_type', 'donation')->count())->toBe(1);
});

test('the onboarding copy of a starter kit wires nothing', function () {
    [, $kit] = kitTrigKit();
    $newcomer = kitTrigUser();

    $kit->fork($newcomer);

    expect(ExternalEventTemplateMapping::where('user_id', $newcomer->id)->count())->toBe(0)
        ->and(EventTemplateMapping::where('user_id', $newcomer->id)->count())->toBe(0);
});

test('the kit page plays every declared event, private alerts included, with samples', function () {
    [, $kit, $kofi, , $scene] = kitTrigKit();

    $this->actingAs(kitTrigUser())->get("/kits/{$kit->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('kits/show')
        ->where('preview.declared', true)
        ->has('preview.alerts', 2)
        ->where('preview.alerts.0.html', $kofi->html)
        ->where('preview.backdrop.html', $scene->html)
        ->has('preview.triggers', 3)
        ->where('preview.triggers.0.label', 'Ko-fi Donation')
        ->where('preview.triggers.0.variants.0.tags', fn ($tags) => $tags['event.source'] === 'Ko-fi')
        ->has('preview.triggers.1.variants', 2)
        ->missing('preview.triggers.0.wiring')
        ->has('preview.sample.channel_name')
    );
});

test('the kit listing no longer carries template source', function () {
    [, $kit] = kitTrigKit();

    $this->actingAs(kitTrigUser())->get("/kits/{$kit->id}")->assertInertia(fn (Assert $page) => $page
        ->has('kit.templates', 3)
        ->missing('kit.templates.0.html')
        ->missing('kit.templates.0.css')
        ->missing('kit.alert_triggers')
        ->has('kit.templates.0.name')
    );
});

test('a kit that declares nothing previews from its author\'s own Triggers', function () {
    [$author, $kit, $kofi, $follow] = kitTrigKit([]);
    ExternalEventTemplateMapping::create(['user_id' => $author->id, 'service' => 'throne', 'event_type' => 'donation', 'overlay_template_id' => $kofi->id, 'enabled' => true, 'duration_ms' => 7000]);

    $this->actingAs(kitTrigUser())->get("/kits/{$kit->id}")->assertInertia(fn (Assert $page) => $page
        ->where('preview.declared', false)
        ->where('preview.triggers.0.service', 'throne')
        ->where('preview.triggers.0.duration_ms', 7000)
        // An alert nothing points at still plays, with a sample tip.
        ->where('preview.triggers.1.template_id', $follow->id)
        ->where('preview.triggers.1.service', null)
    );
});

test('the owner sees what is wired and what is left to connect', function () {
    [, $kit] = kitTrigKit();
    $copier = kitTrigUser();
    $this->actingAs($copier)->post("/kits/{$kit->id}/fork");
    $copy = Kit::where('owner_id', $copier->id)->firstOrFail();
    ExternalIntegration::factory()->create(['user_id' => $copier->id, 'service' => 'streamlabs', 'enabled' => true]);

    $this->actingAs($copier)->get("/kits/{$copy->id}")->assertInertia(fn (Assert $page) => $page
        ->where('preview.triggers.0.wiring.state', 'wired')
        ->where('preview.triggers.0.wiring.connected', false)
        ->where('preview.triggers.0.wiring.connect_url', route('settings.integrations.kofi.show'))
        ->where('preview.triggers.2.wiring.state', 'wired')
        ->where('preview.triggers.2.wiring.connected', true)
    );
});

test('a kit with no alerts has no preview', function () {
    $author = kitTrigUser();
    $kit = Kit::factory()->create(['owner_id' => $author->id, 'is_public' => true]);
    $kit->templates()->attach(kitTrigTemplate($author, 'static')->id);

    $this->actingAs($author)->get("/kits/{$kit->id}")->assertInertia(fn (Assert $page) => $page->where('preview', null));
});
