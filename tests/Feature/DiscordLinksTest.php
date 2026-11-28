<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The "Got questions?" links all read one invite: config('services.discord.invite'),
 * shared to every Inertia page as `discordUrl` (the header, the sidebar, the
 * products rail, the install page and the designer read that prop).
 */
it('keeps the invite in one place', function () {
    expect(config('services.discord.invite'))->toStartWith('https://discord.gg/');
});

it('links the Discord from the homepage promise list', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Got questions?')
        ->assertSee('href="'.config('services.discord.invite').'"', false);
});

it('shares the invite with every app page', function () {
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'discordlinks'.fake()->unique()->randomNumber(5)],
    ]);

    $this->actingAs($user)
        ->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('discordUrl', config('services.discord.invite')));
});
