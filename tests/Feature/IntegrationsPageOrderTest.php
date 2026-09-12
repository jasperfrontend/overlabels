<?php

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The integrations settings page lists every service in the order the page
 * wants, not the order drivers were registered in: anything not connected
 * first, then A-Z by display name. Integrations that belong to an Overlabels
 * product carry the product's slug so the page can give them their own spot.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function integrationsPageServices(): array
{
    $response = test()->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/integrations/index')->has('services'));

    return $response->viewData('page')['props']['services'];
}

test('with nothing connected every service is listed A-Z by name', function () {
    $names = array_column(integrationsPageServices(), 'name');

    $sorted = $names;
    usort($sorted, 'strcasecmp');

    expect($names)->toBe($sorted)
        ->and($names)->toContain('Chat Tower', 'Ko-fi', 'Throne');
});

test('connected services sink below the disconnected ones, each half still A-Z', function () {
    ExternalIntegration::factory()->create(['user_id' => $this->user->id, 'service' => 'kofi']);
    ExternalIntegration::factory()->create(['user_id' => $this->user->id, 'service' => 'bmac']);

    $services = integrationsPageServices();

    $connected = array_column(array_filter($services, fn (array $s) => $s['connected']), 'name');
    $disconnected = array_column(array_filter($services, fn (array $s) => ! $s['connected']), 'name');
    $names = array_column($services, 'name');

    expect($connected)->toBe(['Buy Me a Coffee', 'Ko-fi'])
        ->and($names)->toBe([...$disconnected, ...$connected]);

    $sortedDisconnected = $disconnected;
    usort($sortedDisconnected, 'strcasecmp');
    expect($disconnected)->toBe($sortedDisconnected);
});

/**
 * Twitch Alerts and the chat bot are not external services, but to the person
 * using them they are integrations like any other, so they get a settings page
 * of the same shape and a row in the same list.
 */
test('twitch alerts has a settings page of its own carrying the event list', function () {
    $this->get('/settings/integrations/twitch')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/integrations/twitch')
            ->has('eventsub.supported_events')
            ->where('eventsub.active_count', 0)
        );
});

test('the chat bot has a settings page of its own carrying what it answers', function () {
    $this->get('/settings/integrations/bot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/integrations/bot')
            ->where('bot.enabled', false)
            ->has('bot.command_count')
            ->has('bot.alias_count')
            ->has('bot.builtin_count')
        );
});

test('the index carries the one-line status for the twitch and bot rows', function () {
    $this->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/integrations/index')
            ->has('eventsub.active_count')
            ->has('eventsub.supported_count')
            ->has('bot.command_count')
            ->has('bot.alias_count')
        );
});

test('the checkin and tower integrations carry their product slug and the third-party services carry none', function () {
    $byKey = collect(integrationsPageServices())->keyBy('key');

    expect($byKey['checkin']['product'])->toBe('chat_checkin')
        ->and($byKey['tower']['product'])->toBe('chat_tower');

    foreach (['kofi', 'streamlabs', 'fourthwall', 'bmac', 'throne', 'gps'] as $service) {
        expect($byKey[$service]['product'])->toBeNull($service.' is not a product integration');
    }
});
