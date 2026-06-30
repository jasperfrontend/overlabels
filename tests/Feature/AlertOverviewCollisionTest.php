<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function makeOverviewUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function makeNamedAlert(User $user, string $name): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'name' => $name,
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
    ]);
}

test('two identical cheer conditions flag the later template as shadowed', function () {
    $user = makeOverviewUser();
    $winner = makeNamedAlert($user, 'First Cheer'); // lower id wins the tie
    $loser = makeNamedAlert($user, 'Second Cheer');

    foreach ([$winner, $loser] as $t) {
        EventTemplateMapping::create([
            'user_id' => $user->id, 'event_type' => 'channel.cheer',
            'condition_type' => 'exactly', 'condition_value' => 100,
            'template_id' => $t->id, 'duration_ms' => 5000, 'enabled' => true,
        ]);
    }

    $this->actingAs($user)
        ->get('/alerts')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('events/index')
            ->has('twitchMappings', 2)
            ->where('twitchMappings.0.condition_type', 'exactly')
            ->where('twitchMappings.0.condition_value', 100)
            ->where('twitchMappings.0.condition_unit', 'bits')
            ->where('twitchMappings.0.shadowed_by', null)
            ->where('twitchMappings.1.shadowed_by', 'First Cheer')
        );
});

test('different cheer conditions never collide', function () {
    $user = makeOverviewUser();
    $base = makeNamedAlert($user, 'Base Cheer');
    $big = makeNamedAlert($user, 'Big Cheer');

    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.cheer',
        'condition_type' => null, 'condition_value' => null,
        'template_id' => $base->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.cheer',
        'condition_type' => 'at_least', 'condition_value' => 1000,
        'template_id' => $big->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);

    $this->actingAs($user)
        ->get('/alerts')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('events/index')
            ->has('twitchMappings', 2)
            ->where('twitchMappings.0.shadowed_by', null)
            ->where('twitchMappings.1.shadowed_by', null)
        );
});

test('an exactly 100 does not shadow an at_least 100 (it still fires at other amounts)', function () {
    $user = makeOverviewUser();
    $exact = makeNamedAlert($user, 'Exact 100');
    $atLeast = makeNamedAlert($user, 'At Least 100');

    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.cheer',
        'condition_type' => 'exactly', 'condition_value' => 100,
        'template_id' => $exact->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.cheer',
        'condition_type' => 'at_least', 'condition_value' => 100,
        'template_id' => $atLeast->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);

    $this->actingAs($user)
        ->get('/alerts')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('twitchMappings.0.shadowed_by', null)
            ->where('twitchMappings.1.shadowed_by', null)
        );
});

test('identical donation conditions flag the shadowed external row', function () {
    $user = makeOverviewUser();
    $winner = makeNamedAlert($user, 'First Donation');
    $loser = makeNamedAlert($user, 'Second Donation');

    foreach ([$winner, $loser] as $t) {
        ExternalEventTemplateMapping::create([
            'user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'donation',
            'condition_type' => 'at_least', 'condition_value' => 50,
            'overlay_template_id' => $t->id, 'duration_ms' => 5000, 'enabled' => true,
        ]);
    }

    $this->actingAs($user)
        ->get('/alerts')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('externalMappings', 2)
            ->where('externalMappings.0.condition_unit', 'amount')
            ->where('externalMappings.0.shadowed_by', null)
            ->where('externalMappings.1.shadowed_by', 'First Donation')
        );
});

test('a disabled duplicate does not shadow the enabled one', function () {
    $user = makeOverviewUser();
    $live = makeNamedAlert($user, 'Live Cheer');
    $off = makeNamedAlert($user, 'Disabled Cheer');

    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.cheer',
        'condition_type' => 'exactly', 'condition_value' => 100,
        'template_id' => $live->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.cheer',
        'condition_type' => 'exactly', 'condition_value' => 100,
        'template_id' => $off->id, 'duration_ms' => 5000, 'enabled' => false,
    ]);

    $this->actingAs($user)
        ->get('/alerts')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('twitchMappings', 1)
            ->where('twitchMappings.0.shadowed_by', null)
        );
});
