<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// Account deletion is an Inertia XHR whose destination is '/', a plain Blade
// view. The response has to be a 409 + X-Inertia-Location so the client does a
// real navigation; an ordinary redirect gets followed by the XHR itself, which
// then lands Inertia on a full HTML document it cannot parse. Same shape as
// logout, which had the identical bug.

function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

it('answers an inertia delete with a full-page location to home', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(inertiaHeaders())
        ->delete(route('settings.account.destroy'), ['confirmation' => 'DELETE ACCOUNT']);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toBe(route('home'));
    expect($response->headers->get('Location'))->toBeNull();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertGuest();
});

it('leaves the account alone when the confirmation phrase is wrong', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(inertiaHeaders())
        ->from(route('settings.account'))
        ->delete(route('settings.account.destroy'), ['confirmation' => 'delete account'])
        ->assertRedirect(route('settings.account'))
        ->assertSessionHasErrors('confirmation');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertAuthenticatedAs($user);
});
