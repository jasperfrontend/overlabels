<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot reach the usage page', function () {
    $this->get('/settings/usage')
        ->assertRedirect('/login?redirect_to='.urlencode(url('/settings/usage')));
});

test('the usage page renders with a usage summary and history', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/settings/usage')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Usage')
            ->has('usage', fn (Assert $usage) => $usage
                ->where('period', now()->format('Y-m'))
                ->has('broadcasts')
                ->has('limit')
            )
            ->has('history', 6)
        );
});

test('usage is shared globally so the dashboard strip can render', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->has('usage'));
});
