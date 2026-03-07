<?php

use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeUserAndTemplate(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'test-'.fake()->unique()->lexify('????????'),
    ]);

    return [$user, $template];
}

it('allows owner to set screenshot URL', function () {
    [$user, $template] = makeUserAndTemplate();

    $response = $this->actingAs($user)->put(
        route('templates.screenshot', $template),
        ['screenshot_url' => 'https://res.cloudinary.com/example/image/upload/test.png']
    );

    $response->assertRedirect();
    expect($template->fresh()->screenshot_url)->toBe('https://res.cloudinary.com/example/image/upload/test.png');
});

it('allows owner to remove screenshot', function () {
    [$user, $template] = makeUserAndTemplate();

    $template->update(['screenshot_url' => 'https://example.com/old.png']);

    $response = $this->actingAs($user)->put(
        route('templates.screenshot', $template),
        ['screenshot_url' => null]
    );

    $response->assertRedirect();
    expect($template->fresh()->screenshot_url)->toBeNull();
});

it('rejects non-owner with 403', function () {
    [$owner, $template] = makeUserAndTemplate();
    $other = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $response = $this->actingAs($other)->put(
        route('templates.screenshot', $template),
        ['screenshot_url' => 'https://example.com/screenshot.png']
    );

    $response->assertForbidden();
});

it('rejects invalid URL', function () {
    [$user, $template] = makeUserAndTemplate();

    $response = $this->actingAs($user)->put(
        route('templates.screenshot', $template),
        ['screenshot_url' => 'not-a-url']
    );

    $response->assertSessionHasErrors('screenshot_url');
});

it('redirects unauthenticated users', function () {
    [, $template] = makeUserAndTemplate();

    $response = $this->put(
        route('templates.screenshot', $template),
        ['screenshot_url' => 'https://example.com/screenshot.png']
    );

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
});
