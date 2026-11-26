<?php

use App\Models\DashboardHighlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Dashboard highlights: feature notes written at /admin/highlights and shown
 * on every dashboard, enabled ones only, lowest order first.
 */
function highlightAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function highlightPayload(array $overrides = []): array
{
    return array_merge([
        'body' => 'Your title can keep itself true.',
        'link_url' => '/settings/title',
        'link_label' => 'Set your Living Title',
        'sort_order' => 5,
        'enabled' => true,
    ], $overrides);
}

it('ships with the five first highlights, in order', function () {
    expect(DashboardHighlight::shown()->pluck('link_url')->all())
        ->toBe(['/settings/title', '/settings/bot/commands', '/lists', '/kits', '/recents']);
});

it('shows enabled highlights on the dashboard, lowest order first', function () {
    DashboardHighlight::query()->delete();
    DashboardHighlight::create(highlightPayload(['body' => 'Second', 'sort_order' => 20]));
    DashboardHighlight::create(highlightPayload(['body' => 'First', 'sort_order' => 10]));
    DashboardHighlight::create(highlightPayload(['body' => 'Hidden', 'sort_order' => 1, 'enabled' => false]));

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('highlights', 2)
            ->where('highlights.0.body', 'First')
            ->where('highlights.1.body', 'Second'));
});

it('lets an admin add, edit and delete a highlight', function () {
    $admin = highlightAdmin();

    $this->actingAs($admin)->post('/admin/highlights', highlightPayload())->assertRedirect('/admin/highlights');
    $highlight = DashboardHighlight::where('link_label', 'Set your Living Title')->sole();

    $this->actingAs($admin)->put("/admin/highlights/{$highlight->id}", highlightPayload(['link_label' => 'Changed', 'enabled' => false]))
        ->assertRedirect('/admin/highlights');
    expect($highlight->fresh()->link_label)->toBe('Changed')
        ->and($highlight->fresh()->enabled)->toBeFalse();

    $this->actingAs($admin)->get('/admin/highlights')->assertOk();
    $this->actingAs($admin)->get("/admin/highlights/{$highlight->id}/edit")->assertOk();

    $this->actingAs($admin)->delete("/admin/highlights/{$highlight->id}")->assertRedirect('/admin/highlights');
    expect(DashboardHighlight::find($highlight->id))->toBeNull();
});

it('keeps links inside Overlabels', function (string $url) {
    $this->actingAs(highlightAdmin())
        ->post('/admin/highlights', highlightPayload(['link_url' => $url]))
        ->assertSessionHasErrors('link_url');
})->with(['https://example.com', '//example.com', 'settings/title', '/with space', 'javascript:alert(1)']);

it('is invisible to anyone who is not an admin', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $this->actingAs($user)->get('/admin/highlights')->assertNotFound();
    $this->actingAs($user)->post('/admin/highlights', highlightPayload())->assertNotFound();
});
