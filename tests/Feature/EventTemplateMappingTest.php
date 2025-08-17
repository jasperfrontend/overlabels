<?php

use App\Models\User;
use App\Models\OverlayTemplate;
use App\Models\EventTemplateMapping;

beforeEach(function () {
    $this->user = User::factory()->create([
        'twitch_id' => '123456789',
        'access_token' => 'test_token',
    ]);

    $this->alertTemplate = OverlayTemplate::factory()->create([
        'owner_id' => $this->user->id,
        'type' => 'alert',
        'name' => 'Test Alert Template',
        'html' => '<div class="alert">New follower: [[[event.user_name]]]!</div>',
        'css' => '.alert { color: red; }',
    ]);

    $this->staticTemplate = OverlayTemplate::factory()->create([
        'owner_id' => $this->user->id,
        'type' => 'static',
        'name' => 'Test Static Template',
        'html' => '<div>Followers: [[[followers_total]]]</div>',
        'css' => '',
    ]);
});

test('can create event template mapping', function () {
    $this->actingAs($this->user);

    $response = $this->post(route('events.store'), [
        'event_type' => 'channel.follow',
        'template_id' => $this->alertTemplate->id,
        'duration_ms' => 5000,
        'transition_type' => 'fade',
        'enabled' => true,
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('event_template_mappings', [
        'user_id' => $this->user->id,
        'event_type' => 'channel.follow',
        'template_id' => $this->alertTemplate->id,
        'duration_ms' => 5000,
        'transition_type' => 'fade',
        'enabled' => true,
    ]);
});

test('cannot map static template to event', function () {
    $this->actingAs($this->user);

    $response = $this->post(route('events.store'), [
        'event_type' => 'channel.follow',
        'template_id' => $this->staticTemplate->id,
        'duration_ms' => 5000,
        'transition_type' => 'fade',
        'enabled' => true,
    ]);

    $response->assertStatus(422);
});

test('can view event mapping configuration page', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('events.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('events/Index')
        ->has('mappings')
        ->has('alertTemplates')
        ->has('eventTypes')
        ->has('transitionTypes')
    );
});

test('event template mapping has correct relationships', function () {
    $mapping = EventTemplateMapping::create([
        'user_id' => $this->user->id,
        'event_type' => 'channel.follow',
        'template_id' => $this->alertTemplate->id,
        'duration_ms' => 5000,
        'transition_type' => 'fade',
        'enabled' => true,
    ]);

    expect($mapping->user->id)->toBe($this->user->id);
    expect($mapping->template->id)->toBe($this->alertTemplate->id);
    expect($mapping->template->type)->toBe('alert');
});

test('template data mapper can handle event tags', function () {
    $mapper = app(\App\Services\TemplateDataMapperService::class);

    $eventData = [
        'subscription' => ['type' => 'channel.follow'],
        'event' => [
            'user_id' => '98765',
            'user_login' => 'testuser',
            'user_name' => 'TestUser',
            'broadcaster_user_id' => '123456789',
        ]
    ];

    $twitchData = [
        'user' => ['display_name' => 'StreamerName'],
        'channel_followers' => ['total' => 1000]
    ];

    $result = $mapper->mapForTemplate($twitchData, 'Test', null, $eventData);

    expect($result)->toHaveKey('event.type');
    expect($result)->toHaveKey('event.user_name');
    expect($result['event.type'])->toBe('channel.follow');
    expect($result['event.user_name'])->toBe('TestUser');
});

test('template type filtering works', function () {
    $this->actingAs($this->user);

    // Test filtering by alert type
    $response = $this->get(route('templates.index', ['type' => 'alert']));
    $response->assertStatus(200);

    // Test filtering by static type
    $response = $this->get(route('templates.index', ['type' => 'static']));
    $response->assertStatus(200);
});

test('alert template can be created with event tags', function () {
    $this->actingAs($this->user);

    $response = $this->post(route('templates.store'), [
        'name' => 'New Follower Alert',
        'description' => 'Shows when someone follows',
        'type' => 'alert',
        'html' => '<div>Welcome [[[event.user_name]]]! We now have [[[followers_total]]] followers!</div>',
        'css' => '.alert { background: blue; }',
        'is_public' => true,
    ]);

    $response->assertRedirect();

    $template = OverlayTemplate::where('name', 'New Follower Alert')->first();
    expect($template)->not->toBeNull();
    expect($template->type)->toBe('alert');
    expect($template->html)->toContain('[[[event.user_name]]]');
    expect($template->html)->toContain('[[[followers_total]]]');
});
