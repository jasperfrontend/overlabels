<?php

use App\Models\GeoPlace;
use App\Services\Geo\PlaceResolverService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function seedGeoPlace(array $attributes, array $searchNames): GeoPlace
{
    $place = GeoPlace::create(array_merge([
        'geonames_id' => random_int(1, PHP_INT_MAX),
        'lat' => 0.0,
        'lng' => 0.0,
        'population' => 0,
    ], $attributes));

    foreach ($searchNames as $name) {
        $place->names()->create(['name_normalized' => PlaceResolverService::normalize($name)]);
    }

    return $place;
}

beforeEach(function () {
    PlaceResolverService::flushCountryNameMap();

    seedGeoPlace(
        ['name' => 'Rotterdam', 'ascii_name' => 'Rotterdam', 'country_code' => 'NL', 'lat' => 51.9225, 'lng' => 4.47917, 'population' => 623652],
        ['Rotterdam'],
    );
    seedGeoPlace(
        ['name' => 'Amsterdam', 'ascii_name' => 'Amsterdam', 'country_code' => 'NL', 'lat' => 52.37403, 'lng' => 4.88969, 'population' => 821752],
        ['Amsterdam'],
    );
    seedGeoPlace(
        ['name' => 'The Hague', 'ascii_name' => 'The Hague', 'country_code' => 'NL', 'lat' => 52.07667, 'lng' => 4.29861, 'population' => 474292],
        ['The Hague', 'Den Haag', "'s-Gravenhage"],
    );
    seedGeoPlace(
        ['name' => 'Paris', 'ascii_name' => 'Paris', 'country_code' => 'FR', 'lat' => 48.85341, 'lng' => 2.3488, 'population' => 2138551],
        ['Paris'],
    );
    seedGeoPlace(
        ['name' => 'Paris', 'ascii_name' => 'Paris', 'country_code' => 'US', 'lat' => 33.66094, 'lng' => -95.55551, 'population' => 24171],
        ['Paris'],
    );
    seedGeoPlace(
        ['name' => 'Munich', 'ascii_name' => 'Munich', 'country_code' => 'DE', 'lat' => 48.13743, 'lng' => 11.57549, 'population' => 1260391],
        ['Munich', 'Muenchen', 'Munchen'],
    );

    $this->resolver = new PlaceResolverService;
});

test('resolves a city with an ISO country code hint', function () {
    $place = $this->resolver->resolve('Rotterdam, NL');

    expect($place)->not->toBeNull()
        ->and($place->name)->toBe('Rotterdam')
        ->and($place->countryCode)->toBe('NL')
        ->and($place->lat)->toBe(51.9225)
        ->and($place->label())->toBe('Rotterdam, NL');
});

test('an ambiguous name resolves to the biggest population', function () {
    $place = $this->resolver->resolve('Paris');

    expect($place->countryCode)->toBe('FR');
});

test('a country hint overrides population ranking', function () {
    $place = $this->resolver->resolve('Paris, US');

    expect($place->countryCode)->toBe('US')
        ->and($place->population)->toBe(24171);
});

test('a resolvable country hint never falls back to another country', function () {
    expect($this->resolver->resolve('Rotterdam, US'))->toBeNull();
});

test('resolves an alternate name', function () {
    $place = $this->resolver->resolve('Den Haag');

    expect($place->name)->toBe('The Hague');
});

test('input with diacritics matches through normalization', function () {
    $place = $this->resolver->resolve('München');

    expect($place->name)->toBe('Munich');
});

test('case and whitespace do not matter', function () {
    $place = $this->resolver->resolve('  ROTTERDAM  ');

    expect($place->name)->toBe('Rotterdam');
});

test('a country name works as a hint', function () {
    $place = $this->resolver->resolve('Rotterdam, Netherlands');

    expect($place->countryCode)->toBe('NL');
});

test('a colloquial country alias works as a hint', function () {
    $place = $this->resolver->resolve('Paris, USA');

    expect($place->countryCode)->toBe('US');
});

test('an unresolvable comma tail falls back to the head', function () {
    $place = $this->resolver->resolve('Rotterdam, Zuid-Holland');

    expect($place->name)->toBe('Rotterdam');
});

test('a typo resolves through fuzzy matching', function () {
    $place = $this->resolver->resolve('amsterdamm');

    expect($place->name)->toBe('Amsterdam');
});

test('garbage input is a miss, not a guess', function () {
    // Reproduces the real-world case that calibrated the fuzzy threshold:
    // Geita's short alias "gya" scores 0.5 against "gyat", which must lose.
    seedGeoPlace(
        ['name' => 'Geita', 'ascii_name' => 'Geita', 'country_code' => 'TZ', 'population' => 318006],
        ['Geita', 'gya'],
    );

    expect($this->resolver->resolve('gyat'))->toBeNull();
});

test('empty and whitespace input resolve to null', function () {
    expect($this->resolver->resolve(''))->toBeNull()
        ->and($this->resolver->resolve('   '))->toBeNull();
});

test('an absurdly long query resolves to null', function () {
    expect($this->resolver->resolve(str_repeat('a', 500)))->toBeNull();
});
