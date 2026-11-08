<?php

use App\Services\Recipes\RecipeCatalog;
use Inertia\Testing\AssertableInertia as Assert;

// The login page names the product a visitor was about to install, so it can
// tell them why it wants their Twitch account. Only our own install page for a
// listed product may do that, and the name always comes from the catalogue.

function listedProduct(): array
{
    return collect(app(RecipeCatalog::class)->listed())->where('category', 'product')->first();
}

test('the login page names the product when it is reached from that product install page', function () {
    $product = listedProduct();
    $redirect = url("/products/{$product['slug']}/install");

    $this->get('/login?redirect_to='.urlencode($redirect))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('installing.slug', $product['slug'])
            ->where('installing.name', $product['name']));
});

test('a site-relative install path counts too', function () {
    $product = listedProduct();

    $this->get('/login?redirect_to='.urlencode("/products/{$product['slug']}/install"))
        ->assertInertia(fn (Assert $page) => $page->where('installing.name', $product['name']));
});

test('the login page names nothing for anything other than our own listed install page', function (string $redirect) {
    $this->get('/login?redirect_to='.urlencode($redirect))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('installing', null));
})->with([
    'another host' => fn () => 'https://evil.example/products/'.listedProduct()['slug'].'/install',
    'protocol-relative host' => fn () => '//evil.example/products/'.listedProduct()['slug'].'/install',
    'unknown product' => fn () => url('/products/not-a-real-product/install'),
    'product pitch page' => fn () => url('/products/'.listedProduct()['slug']),
    'dashboard' => fn () => url('/dashboard'),
]);

test('the login page names nothing without a redirect', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('installing', null));
});
