<?php

use App\Models\PaddleCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config([
        'services.paddle.api_key' => 'pdl_sdbx_test',
        'services.paddle.environment' => 'sandbox',
    ]);
});

it('does not reach Paddle for a guest', function () {
    Http::fake();

    $this->get('/settings/billing/portal')->assertRedirect();

    Http::assertNothingSent();
});

it('404s for a user who has never bought anything', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())->get('/settings/billing/portal')->assertNotFound();

    Http::assertNothingSent();
});

it('mints a portal session for the signed-in user\'s own customer and redirects to it', function () {
    $user = User::factory()->create();
    PaddleCustomer::create(['customer_id' => 'ctm_mine', 'user_id' => $user->id]);
    PaddleCustomer::create(['customer_id' => 'ctm_someone_else', 'user_id' => User::factory()->create()->id]);

    Http::fake(['sandbox-api.paddle.com/*' => Http::response([
        'data' => ['urls' => ['general' => ['overview' => 'https://customer-portal.paddle.com/cpl_abc']]],
    ])]);

    $this->actingAs($user)
        ->get('/settings/billing/portal?customer_id=ctm_someone_else')
        ->assertRedirect('https://customer-portal.paddle.com/cpl_abc');

    Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://sandbox-api.paddle.com/customers/ctm_mine/portal-sessions'
        && $r->hasHeader('Authorization', 'Bearer pdl_sdbx_test'));
});

it('answers 502 when Paddle refuses, instead of redirecting anywhere', function () {
    $user = User::factory()->create();
    PaddleCustomer::create(['customer_id' => 'ctm_mine', 'user_id' => $user->id]);

    Http::fake(['sandbox-api.paddle.com/*' => Http::response(['error' => ['code' => 'forbidden']], 403)]);

    $this->actingAs($user)->get('/settings/billing/portal')->assertStatus(502);
});
