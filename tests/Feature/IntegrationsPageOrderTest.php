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

test('the checkin and tower integrations carry their product slug and the third-party services carry none', function () {
    $byKey = collect(integrationsPageServices())->keyBy('key');

    expect($byKey['checkin']['product'])->toBe('chat_checkin')
        ->and($byKey['tower']['product'])->toBe('chat_tower');

    foreach (['kofi', 'streamlabs', 'fourthwall', 'bmac', 'throne', 'gps'] as $service) {
        expect($byKey[$service]['product'])->toBeNull($service.' is not a product integration');
    }
});
