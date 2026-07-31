<?php

use App\Models\Update;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUpdate(array $attributes = []): Update
{
    return Update::create(array_merge([
        'title' => 'Overlabels development highlights July 2026',
        'body' => 'Here is what shipped.',
        'excerpt' => 'A month of shipping.',
        'published_at' => now()->subDay(),
    ], $attributes));
}

it('lets a guest read the updates index', function () {
    makeUpdate();

    $this->get('/updates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('updates/index'));
});

it('lets a guest read a single update', function () {
    $update = makeUpdate();

    $this->get("/updates/{$update->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('updates/show')
            ->where('update.slug', $update->slug)
        );
});

it('renders updates as a guest rather than redirecting to login', function () {
    $update = makeUpdate();

    $this->get("/updates/{$update->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user', null)
            ->where('isAdmin', false)
        );
});

it('hides future-dated posts from guests', function () {
    $scheduled = makeUpdate([
        'title' => 'Not out yet',
        'published_at' => now()->addWeek(),
    ]);

    $this->get("/updates/{$scheduled->slug}")->assertNotFound();
});
