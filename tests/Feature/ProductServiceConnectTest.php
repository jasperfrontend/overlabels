<?php

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * A product's service connects on the product page itself, with the one
 * thing that connects it right on the row: Ko-fi's token field, Buy Me a
 * Coffee's link and then its secret, Throne's one click, and for Streamlabs
 * and Fourthwall a consent screen that comes back to the product page. A
 * page that pointed at the Integrations page to "make it work" was the bug
 * (2026-09-15); these pin the connect staying on the page.
 */
function connectUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'connecttester'.fake()->unique()->randomNumber(5)],
    ]);
}

function installProductFor(User $user, string $slug): void
{
    test()->actingAs($user)->post("/products/{$slug}/install")->assertSessionHasNoErrors();
}

it('hands the Ko-fi product its one row, not connected until the token is in, with the connect on the row', function () {
    $user = connectUser();
    installProductFor($user, 'ko-fi-alerts');
    $kofi = ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->firstOrFail();

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->has('installed.services', 1)
            ->where('installed.services.0.service', 'kofi')
            ->where('installed.services.0.label', 'Ko-fi')
            ->where('installed.services.0.kind', 'token')
            // The install made the row, but a Ko-fi row without its token
            // cannot receive anything, so it is not a connection yet.
            ->where('installed.services.0.connected', false)
            ->where('installed.services.0.has_credential', false)
            ->where('installed.services.0.received', false)
            ->where('installed.services.0.webhook_url', url("/api/webhooks/kofi/{$kofi->webhook_token}"))
            ->where('installed.services.0.connect_url', route('settings.integrations.kofi.save'))
            ->where('installed.services.0.settings_url', route('settings.integrations.kofi.show'))
            ->where('installed.services.0.test_guide.label', 'Open Ko-fi webhooks')
        );
});

it('hands the Streamlabs product a one-click connect that comes back to the product page', function () {
    $user = connectUser();
    installProductFor($user, 'streamlabs-alerts');

    $this->actingAs($user->fresh())
        ->get('/products/streamlabs-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->has('installed.services', 1)
            ->where('installed.services.0.service', 'streamlabs')
            ->where('installed.services.0.kind', 'oauth')
            ->where('installed.services.0.connected', false)
            ->where('installed.services.0.webhook_url', null)
            ->where('installed.services.0.connect_url', route('settings.integrations.streamlabs.redirect', ['return_to' => '/products/streamlabs-alerts']))
        );
});

it('connects Ko-fi from the product page and lands back on it with the row ticked', function () {
    $user = connectUser();
    installProductFor($user, 'ko-fi-alerts');

    $this->actingAs($user)
        ->from('/products/ko-fi-alerts')
        ->post(route('settings.integrations.kofi.save'), ['verification_token' => 'kofi-token'])
        ->assertRedirect('/products/ko-fi-alerts');

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.services.0.connected', true)
            ->where('installed.services.0.has_credential', true)
            ->where('installed.services.0.received', false)
            // The integration wire reads the same fact, so the checklist agrees.
            ->where('installed.subject.wires', fn ($wires) => collect($wires)->firstWhere('key', 'product.integration')['state'] === 'satisfied')
        );
});

it('refuses an empty Ko-fi token back on the product page, naming the field', function () {
    $user = connectUser();
    installProductFor($user, 'ko-fi-alerts');

    $this->actingAs($user)
        ->from('/products/ko-fi-alerts')
        ->post(route('settings.integrations.kofi.save'), ['verification_token' => ''])
        ->assertRedirect('/products/ko-fi-alerts')
        ->assertSessionHasErrors('verification_token');
});

it('connects Throne by the install itself and shows the link to paste, and reconnects in one click if the row went', function () {
    $user = connectUser();
    installProductFor($user, 'throne-alerts');
    $throne = ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->firstOrFail();

    $this->actingAs($user->fresh())
        ->get('/products/throne-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.services.0.kind', 'none')
            ->where('installed.services.0.connected', true)
            ->where('installed.services.0.received', false)
            ->where('installed.services.0.webhook_url', url("/api/webhooks/throne/{$throne->webhook_token}"))
            ->where('installed.services.0.connect_url', route('settings.integrations.throne.connect'))
        );

    $throne->delete();
    $this->actingAs($user->fresh())
        ->get('/products/throne-alerts')
        ->assertInertia(fn (Assert $page) => $page->where('installed.services.0.connected', false)->where('installed.services.0.webhook_url', null));

    $this->actingAs($user)
        ->from('/products/throne-alerts')
        ->post(route('settings.integrations.throne.connect'))
        ->assertRedirect('/products/throne-alerts');

    $this->actingAs($user->fresh())
        ->get('/products/throne-alerts')
        ->assertInertia(fn (Assert $page) => $page->where('installed.services.0.connected', true));
});

it('shows Buy Me a Coffee its link from the install, then takes the secret on the product page', function () {
    $user = connectUser();
    installProductFor($user, 'buy-me-a-coffee-alerts');
    $bmac = ExternalIntegration::where('user_id', $user->id)->where('service', 'bmac')->firstOrFail();

    $this->actingAs($user->fresh())
        ->get('/products/buy-me-a-coffee-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.services.0.kind', 'secret')
            ->where('installed.services.0.connected', false)
            ->where('installed.services.0.has_credential', false)
            ->where('installed.services.0.webhook_url', url("/api/webhooks/bmac/{$bmac->webhook_token}"))
        );

    $this->actingAs($user)
        ->from('/products/buy-me-a-coffee-alerts')
        ->post(route('settings.integrations.bmac.save'), ['webhook_secret' => 'bmac-secret'])
        ->assertRedirect('/products/buy-me-a-coffee-alerts');

    $this->actingAs($user->fresh())
        ->get('/products/buy-me-a-coffee-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.services.0.connected', true)
            ->where('installed.services.0.has_credential', true)
        );
});

it('brings a Streamlabs connect started from the product page back to it', function () {
    $user = connectUser();
    installProductFor($user, 'streamlabs-alerts');

    $this->actingAs($user)
        ->get(route('settings.integrations.streamlabs.redirect', ['return_to' => '/products/streamlabs-alerts']))
        ->assertRedirect()
        ->assertSessionHas('integration_return_to.streamlabs', '/products/streamlabs-alerts');

    Http::fake([
        'streamlabs.com/api/v2.0/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'token_type' => 'Bearer']),
        'streamlabs.com/api/v2.0/socket/token' => Http::response(['socket_token' => 'st']),
    ]);

    $this->actingAs($user)
        ->withSession(['streamlabs_oauth_state' => 'state-1', 'integration_return_to.streamlabs' => '/products/streamlabs-alerts'])
        ->get('/auth/callback/streamlabs?code=code-1&state=state-1')
        ->assertRedirect('/products/streamlabs-alerts')
        ->assertSessionHas('success')
        ->assertSessionMissing('integration_return_to.streamlabs');

    $this->actingAs($user->fresh())
        ->get('/products/streamlabs-alerts')
        ->assertInertia(fn (Assert $page) => $page->where('installed.services.0.connected', true));
});

it('brings a cancelled Streamlabs connect back to the product page too, with the error', function () {
    $user = connectUser();

    $this->actingAs($user)
        ->withSession(['integration_return_to.streamlabs' => '/products/streamlabs-alerts'])
        ->get('/auth/callback/streamlabs')
        ->assertRedirect('/products/streamlabs-alerts')
        ->assertSessionHas('error')
        ->assertSessionMissing('integration_return_to.streamlabs');
});

it('ignores a return_to that is not a path on this site, and the settings page button clears a stale one', function (string $returnTo) {
    $user = connectUser();

    $this->actingAs($user)
        ->withSession(['integration_return_to.streamlabs' => '/products/streamlabs-alerts'])
        ->get(route('settings.integrations.streamlabs.redirect', ['return_to' => $returnTo]))
        ->assertRedirect()
        ->assertSessionMissing('integration_return_to.streamlabs');
})->with([
    'off-site' => 'https://evil.example/',
    'protocol-relative' => '//evil.example/',
    'backslash' => '/\\evil.example',
    'none' => '',
]);

it('remembers where a Fourthwall connect came from, and returns there when Fourthwall is not configured', function () {
    $user = connectUser();
    config(['services.fourthwall.auth_url' => null, 'services.fourthwall.redirect_url' => null]);

    $this->actingAs($user)
        ->get(route('settings.integrations.fourthwall.redirect', ['return_to' => '/products/fourthwall-alerts']))
        ->assertRedirect('/products/fourthwall-alerts')
        ->assertSessionHas('error')
        ->assertSessionMissing('integration_return_to.fourthwall');
});

it('gives a product with no external alert no service rows', function () {
    $user = connectUser();
    installProductFor($user, 'chat-tower');

    $this->actingAs($user->fresh())
        ->get('/products/chat-tower')
        ->assertInertia(fn (Assert $page) => $page->where('installed.services', []));
});
