<?php

use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/*
 * A new account lands on the dashboard with nothing on stream. "New" means no
 * product installed, however old the account is; the dialog opens once, and
 * again only after a month of still having nothing installed. The card stays
 * until the first install.
 */

function welcomeUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'welcome'.fake()->unique()->randomNumber(5)],
    ]);
}

it('greets an account with no products with the card and the dialog', function () {
    $this->freezeTime();
    $user = welcomeUser();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('isNewUser', true)
            ->where('showWelcomeDialog', true));

    expect($user->fresh()->preference('welcome_shown_at'))->toBe(now()->timestamp);
});

it('opens the dialog once but keeps the card on the next visit', function () {
    $user = welcomeUser();

    $this->actingAs($user)->get('/dashboard');

    $this->actingAs($user->fresh())->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('isNewUser', true)
            ->where('showWelcomeDialog', false));
});

it('opens the dialog again for an account still without products a month later', function () {
    $user = welcomeUser();
    $user->setPreference('welcome_shown_at', now()->subDays(31)->timestamp)->save();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('showWelcomeDialog', true));
});

it('does not reopen the dialog within the month', function () {
    $user = welcomeUser();
    $user->setPreference('welcome_shown_at', now()->subDays(10)->timestamp)->save();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('showWelcomeDialog', false));
});

it('shows neither once a product is installed, and records nothing', function () {
    $user = welcomeUser();
    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat-checkin')), $user, RecipeInstance::instanceSlugFrom('chat-checkin'));

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('isNewUser', false)
            ->where('showWelcomeDialog', false));

    expect($user->fresh()->preference('welcome_shown_at'))->toBeNull();
});
