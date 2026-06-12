<?php

use Illuminate\Support\Facades\Route;

test('a 419 on an Inertia request is converted to a login location redirect', function () {
    Route::post('/__test/419', fn () => abort(419))->middleware('web');

    $response = $this->withHeader('X-Inertia', 'true')->post('/__test/419');

    // Inertia::location() returns a 409 with the destination in X-Inertia-Location,
    // which the client turns into a full-page visit to login.
    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toContain('/login');
});

test('a 419 on a non-Inertia request keeps the default 419 response', function () {
    Route::post('/__test/419', fn () => abort(419))->middleware('web');

    $response = $this->post('/__test/419');

    $response->assertStatus(419);
});
