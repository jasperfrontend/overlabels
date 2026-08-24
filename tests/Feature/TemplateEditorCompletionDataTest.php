<?php

/**
 * The template editor's tag autocomplete is fed from page props and the tag
 * catalogue endpoint. These pin the data reaching it: a new template's editor
 * gets the same user-scoped controls and Lists an existing one does, and the
 * catalogue response carries the event.* tags that have no catalogue entry.
 */

use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create(['access_token' => 'token']);
});

it('hands the create page the user-scoped service controls and Lists', function () {
    OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => null,
        'key' => 'donations_received',
        'source' => 'kofi',
        'source_managed' => true,
    ]);
    OptionSet::factory()->create(['user_id' => $this->user->id, 'slug' => 'donors', 'label' => 'Donors']);

    $this->actingAs($this->user)
        ->get('/templates/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/create')
            ->has('userScopedControls', 1)
            ->where('userScopedControls.0.key', 'donations_received')
            ->where('userScopedControls.0.source', 'kofi')
            ->has('userLists', 1)
            ->where('userLists.0.slug', 'donors')
            ->where('userLists.0.label', 'Donors')
        );
});

it('does not hand the create page another user\'s controls or template-scoped ones', function () {
    $other = User::factory()->create();
    OverlayControl::factory()->create([
        'user_id' => $other->id,
        'overlay_template_id' => null,
        'key' => 'theirs',
        'source' => 'kofi',
        'source_managed' => true,
    ]);
    $template = OverlayTemplate::factory()->create(['owner_id' => $this->user->id]);
    OverlayControl::factory()->create([
        'user_id' => $this->user->id,
        'overlay_template_id' => $template->id,
        'key' => 'template_only',
    ]);
    OptionSet::factory()->create(['user_id' => $other->id, 'slug' => 'not_mine']);

    $this->actingAs($this->user)
        ->get('/templates/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('userScopedControls', 0)
            ->has('userLists', 0)
        );
});

it('serves the event tags alongside the catalogue', function () {
    $response = $this->actingAs($this->user)->getJson('/api/template-tags')->assertOk();

    $eventTags = $response->json('event_tags');

    expect($eventTags)->toBeArray()
        ->and($eventTags)->toContain('event.user_name', 'event.bits', 'event.type')
        ->and(collect($eventTags)->every(fn ($tag) => str_starts_with($tag, 'event.')))->toBeTrue();
});
