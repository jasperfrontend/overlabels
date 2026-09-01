<?php

use App\Models\GeoPlace;
use App\Models\GeoPlaceName;
use App\Services\Geo\GeoNamesImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Builds a cities500-shaped zip fixture: 19 tab-separated columns per line,
 * matching the columns the importer reads (0 id, 1 name, 2 ascii,
 * 3 alternates, 4 lat, 5 lng, 8 country, 14 population).
 */
function makeGeoNamesZip(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'geotest_').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('cities500.txt', implode("\n", $lines)."\n");
    $zip->close();

    return $path;
}

function geoNamesLine(int $id, string $name, string $ascii, string $alternates, float $lat, float $lng, string $cc, int $pop): string
{
    $cols = array_fill(0, 19, '');
    $cols[0] = (string) $id;
    $cols[1] = $name;
    $cols[2] = $ascii;
    $cols[3] = $alternates;
    $cols[4] = (string) $lat;
    $cols[5] = (string) $lng;
    $cols[6] = 'P';
    $cols[7] = 'PPL';
    $cols[8] = $cc;
    $cols[14] = (string) $pop;
    $cols[17] = 'Europe/Amsterdam';
    $cols[18] = '2026-01-01';

    return implode("\t", $cols);
}

test('imports places and searchable names from a cities500 archive', function () {
    $zip = makeGeoNamesZip([
        geoNamesLine(2747891, 'Rotterdam', 'Rotterdam', 'Roterdam,Роттердам', 51.9225, 4.47917, 'NL', 623652),
        geoNamesLine(2867714, 'München', 'Munich', 'Muenchen,Munchen', 48.13743, 11.57549, 'DE', 1260391),
    ]);

    $result = (new GeoNamesImporter)->import($zip);

    expect($result['lines'])->toBe(2)
        ->and($result['places'])->toBe(2);

    $rotterdam = GeoPlace::where('geonames_id', 2747891)->first();
    expect($rotterdam)->not->toBeNull()
        ->and($rotterdam->country_code)->toBe('NL')
        ->and($rotterdam->population)->toBe(623652);

    $names = $rotterdam->names()->pluck('name_normalized')->all();
    // "Роттердам" transliterates to "rotterdam" and dedupes into the primary name.
    expect($names)->toContain('rotterdam')
        ->and($names)->toContain('roterdam');

    $munich = GeoPlace::where('geonames_id', 2867714)->first();
    expect($munich->names()->pluck('name_normalized')->all())
        ->toContain('munchen')
        ->toContain('muenchen')
        ->toContain('munich');

    unlink($zip);
});

test('re-running the import is idempotent', function () {
    $zip = makeGeoNamesZip([
        geoNamesLine(2747891, 'Rotterdam', 'Rotterdam', 'Roterdam', 51.9225, 4.47917, 'NL', 623652),
    ]);

    $importer = new GeoNamesImporter;
    $importer->import($zip);
    $placeCount = GeoPlace::count();
    $nameCount = GeoPlaceName::count();

    $second = $importer->import($zip);

    expect(GeoPlace::count())->toBe($placeCount)
        ->and(GeoPlaceName::count())->toBe($nameCount)
        ->and($second['names'])->toBe(0);

    unlink($zip);
});

test('malformed and nameless lines are skipped', function () {
    $zip = makeGeoNamesZip([
        'not a real line',
        geoNamesLine(0, 'No id', 'No id', '', 0, 0, 'NL', 1),
        geoNamesLine(2747891, 'Rotterdam', 'Rotterdam', '', 51.9225, 4.47917, 'NL', 623652),
    ]);

    $result = (new GeoNamesImporter)->import($zip);

    expect($result['places'])->toBe(1)
        ->and(GeoPlace::where('geonames_id', 2747891)->exists())->toBeTrue()
        ->and(GeoPlace::where('geonames_id', 0)->exists())->toBeFalse();

    unlink($zip);
});
