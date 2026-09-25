<?php

use App\Http\Controllers\Api\CheckinResolveController;
use App\Models\GeoPlace;
use App\Services\Geo\PlaceResolverService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

uses(DatabaseTransactions::class);

const CHECKIN_RESOLVE_URL = '/api/checkin/resolve';

const CHECKIN_RESOLVE_MISS_REPLY = "Couldn't find that place. Try City, CC - like Rotterdam, NL";

function seedResolvePlace(string $name, string $cc, float $lat, float $lng, int $population): GeoPlace
{
    $place = GeoPlace::create([
        'geonames_id' => random_int(1, PHP_INT_MAX),
        'name' => $name,
        'ascii_name' => $name,
        'country_code' => $cc,
        'lat' => $lat,
        'lng' => $lng,
        'population' => $population,
    ]);
    $place->names()->create(['name_normalized' => PlaceResolverService::normalize($name)]);

    return $place;
}

beforeEach(function () {
    Cache::flush();
    PlaceResolverService::flushCountryNameMap();

    seedResolvePlace('Avarua', 'CK', -21.2075, -159.77546, 5445);
    seedResolvePlace('Rotterdam', 'NL', 51.9225, 4.47917, 623652);
    seedResolvePlace('Osaka', 'JP', 34.69374, 135.50218, 2753862);
});

test('a known place resolves to the full place shape', function () {
    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua')
        ->assertOk()
        ->assertExactJson([
            'place' => [
                'name' => 'Avarua',
                'country_code' => 'CK',
                'country' => 'Cook Islands',
                'label' => 'Avarua, CK',
                'lat' => -21.2075,
                'lng' => -159.77546,
            ],
        ]);
});

test('a country hint scopes the lookup like it does in chat', function () {
    $this->getJson(CHECKIN_RESOLVE_URL.'?q='.urlencode('Osaka, JP'))
        ->assertOk()
        ->assertJsonPath('place.label', 'Osaka, JP');

    $this->getJson(CHECKIN_RESOLVE_URL.'?q='.urlencode('Osaka, NL'))
        ->assertNotFound();
});

test('a miss returns 404 with the exact reply the bot speaks', function () {
    // Three characters is below the resolver's FUZZY_MIN_LENGTH (4), so
    // this cannot land on anything through trigram matching.
    $this->getJson(CHECKIN_RESOLVE_URL.'?q=zqx')
        ->assertNotFound()
        ->assertExactJson([
            'place' => null,
            'reply' => CHECKIN_RESOLVE_MISS_REPLY,
        ]);
});

test('a missing q is rejected with 422 in the contract shape', function () {
    $this->getJson(CHECKIN_RESOLVE_URL)
        ->assertStatus(422)
        ->assertJsonPath('place', null)
        ->assertJsonStructure(['place', 'reply']);
});

test('a blank q is rejected with 422', function () {
    $this->getJson(CHECKIN_RESOLVE_URL.'?q='.urlencode('   '))
        ->assertStatus(422)
        ->assertJsonPath('place', null);
});

test('a 121-character q is rejected and 120 is not', function () {
    $this->getJson(CHECKIN_RESOLVE_URL.'?q='.str_repeat('a', 121))
        ->assertStatus(422)
        ->assertJsonPath('place', null);

    $this->getJson(CHECKIN_RESOLVE_URL.'?q='.str_repeat('a', 120))
        ->assertNotFound();
});

test('a second identical request is served from the cache', function () {
    $key = CheckinResolveController::cacheKey('avarua');

    expect(Cache::has($key))->toBeFalse();

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua')->assertOk();

    expect(Cache::has($key))->toBeTrue();

    // With the gazetteer gone, only the cache can still answer. Case and
    // surrounding whitespace collapse onto the same key.
    GeoPlace::query()->delete();

    $this->getJson(CHECKIN_RESOLVE_URL.'?q='.urlencode('  AVARUA '))
        ->assertOk()
        ->assertJsonPath('place.label', 'Avarua, CK');
});

test('a miss is cached for minutes, not for a day', function () {
    $key = CheckinResolveController::cacheKey('zqx');

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=zqx')->assertNotFound();

    expect(Cache::has($key))->toBeTrue();

    // The gazetteer learns the place, but the hot miss is still served.
    seedResolvePlace('Zqx', 'NL', 0.0, 0.0, 1);

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=zqx')->assertNotFound();

    // Six minutes on, the miss key is gone while a hit's key would not be:
    // a never-repeated junk query must not hold a Redis key for 24 hours.
    $this->travel(6)->minutes();

    expect(Cache::has($key))->toBeFalse();

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=zqx')->assertOk();
});

test('a hit stays cached well past the miss window', function () {
    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua')->assertOk();

    $this->travel(6)->minutes();

    expect(Cache::has(CheckinResolveController::cacheKey('avarua')))->toBeTrue();
});

test('a browser on another site gets a 404, the homepage does not', function () {
    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua', ['Origin' => 'https://example.com'])
        ->assertNotFound();

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua', ['Sec-Fetch-Site' => 'cross-site'])
        ->assertNotFound();

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua', ['Origin' => config('app.url'), 'Sec-Fetch-Site' => 'same-origin'])
        ->assertOk();

    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Avarua', ['Sec-Fetch-Site' => 'none'])
        ->assertOk();
});

test('the route needs no session, token or auth', function () {
    $route = Route::getRoutes()->getByName('api.checkin.resolve');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('api/checkin/resolve')
        ->and($route->excludedMiddleware())->toContain(EnsureFrontendRequestsAreStateful::class);

    foreach ($route->gatherMiddleware() as $middleware) {
        expect($middleware)->not->toStartWith('auth');
    }

    // And the proof: a bare client with no cookie and no token gets an answer.
    $this->flushSession();
    $this->getJson(CHECKIN_RESOLVE_URL.'?q=Rotterdam')->assertOk();
});

test('the route declares its own per-IP throttle', function () {
    $route = Route::getRoutes()->getByName('api.checkin.resolve');

    expect($route->gatherMiddleware())->toContain('throttle:30,1');
});
