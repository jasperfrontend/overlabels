<?php

use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchScopeService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

// Every /settings/title endpoint ends in a Helix call, and three of them in a
// PATCH to the streamer's channel. When the feature shipped (OL-2609-026) none
// of them were limited: a session could fire the save endlessly and Overlabels
// would relay every one to Twitch. These pin the two limiters so that cannot
// silently come back.

beforeEach(function () {
    RateLimiter::clear('twitch-write');
    RateLimiter::clear('twitch-read');
    Queue::fake();
    Http::fake();

    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('searchCategories')->andReturn([]);
        $mock->shouldReceive('updateChannel')->andReturnTrue();
        $mock->shouldReceive('clearChannelInfoCaches');
    });
});

function titleRlUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
        'twitch_scopes' => TwitchScopeService::REQUIRED_SCOPES,
    ]);
}

test('saving the title is limited to 10 a minute per user', function () {
    $this->actingAs(titleRlUser());

    foreach (range(1, 10) as $i) {
        $this->patch('/settings/title', ['enabled' => true, 'template' => "take $i"])->assertRedirect();
    }

    $this->patch('/settings/title', ['enabled' => true, 'template' => 'take 11'])->assertStatus(429);
});

test('resume and the category picker share the write bucket with the save', function () {
    $this->actingAs(titleRlUser());

    foreach (range(1, 5) as $i) {
        $this->post('/settings/title/resume')->assertRedirect();
        $this->post('/settings/title/category', ['id' => '27471', 'name' => 'Minecraft'])->assertRedirect();
    }

    $this->patch('/settings/title', ['enabled' => true, 'template' => 'x'])->assertStatus(429);
});

test('the preview and the category search are limited to 30 a minute per user', function () {
    $this->actingAs(titleRlUser());

    foreach (range(1, 15) as $i) {
        $this->postJson('/settings/title/preview', ['template' => 'x'])->assertOk();
        $this->getJson('/settings/title/categories?q=mine')->assertOk();
    }

    $this->getJson('/settings/title/categories?q=mine')->assertStatus(429);
});

test('the buckets are per user, so one account cannot starve another', function () {
    $this->actingAs(titleRlUser());
    foreach (range(1, 10) as $i) {
        $this->patch('/settings/title', ['enabled' => true, 'template' => 'x'])->assertRedirect();
    }
    $this->patch('/settings/title', ['enabled' => true, 'template' => 'x'])->assertStatus(429);

    $this->actingAs(titleRlUser());
    $this->patch('/settings/title', ['enabled' => true, 'template' => 'x'])->assertRedirect();
});
