<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('guests cannot reach the controls page', function () {
    $this->get('/settings/controls')
        ->assertRedirect('/login?redirect_to='.urlencode(url('/settings/controls')));
});

test('the controls page groups controls by key with scope and overlays', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null]);

    OverlayControl::create([
        'user_id' => $user->id, 'overlay_template_id' => null, 'source' => 'kofi',
        'key' => 'donations_received', 'type' => 'counter', 'value' => '3',
        'source_managed' => true, 'sort_order' => 0,
    ]);
    OverlayControl::create([
        'user_id' => $user->id, 'overlay_template_id' => $template->id,
        'key' => 'wins', 'type' => 'counter', 'value' => '7', 'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->get('/settings/controls')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Controls')
            ->has('groups', 2)
            ->has('groups.0', fn (Assert $group) => $group
                ->has('key')
                ->has('user_scoped')
                ->has('overlays')
                ->has('instances')
                ->etc()
            )
        );
});
