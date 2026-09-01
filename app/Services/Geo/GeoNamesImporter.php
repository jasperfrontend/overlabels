<?php

namespace App\Services\Geo;

use App\Models\GeoPlace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Streams the GeoNames cities500 dump into geo_places / geo_place_names.
 *
 * The TSV is read line by line - never slurped - because this may run inside
 * a queue worker's memory limit on a small box. Idempotent: places upsert on
 * geonames_id, names insertOrIgnore against the unique constraint, so
 * re-running refreshes the gazetteer in place. A name a place no longer
 * carries after a refresh lingers as a harmless extra alias; the periodic
 * full re-import is the only maintenance this data ever needs.
 *
 * Alternate names are kept only when they normalize to a plausible ASCII
 * name - that is what makes "den haag" and transliterations like "moskva"
 * findable while keeping the table in the low hundreds of thousands of rows.
 */
class GeoNamesImporter
{
    public const string DEFAULT_URL = 'https://download.geonames.org/export/dump/cities500.zip';

    private const string INNER_FILE = 'cities500.txt';

    private const int BATCH_SIZE = 500;

    /**
     * @param  string|null  $source  Local .zip path or URL; defaults to the GeoNames download.
     * @param  callable(int $lines, int $places, int $names): void|null  $progress  Called once per batch.
     * @return array{lines: int, places: int, names: int}
     */
    public function import(?string $source = null, ?callable $progress = null): array
    {
        $source ??= self::DEFAULT_URL;

        $zipPath = $this->isUrl($source) ? $this->download($source) : $source;

        if (! is_file($zipPath)) {
            throw new RuntimeException("GeoNames archive not found at {$zipPath}");
        }

        $handle = fopen('zip://'.$zipPath.'#'.self::INNER_FILE, 'r');

        if ($handle === false) {
            throw new RuntimeException('Could not open '.self::INNER_FILE.' inside the archive');
        }

        $lines = 0;
        $places = 0;
        $names = 0;
        $batch = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $lines++;
                $row = $this->parseLine($line);

                if ($row === null) {
                    continue;
                }

                $batch[] = $row;

                if (count($batch) >= self::BATCH_SIZE) {
                    [$p, $n] = $this->flush($batch);
                    $places += $p;
                    $names += $n;
                    $batch = [];

                    if ($progress) {
                        $progress($lines, $places, $names);
                    }
                }
            }

            if ($batch !== []) {
                [$p, $n] = $this->flush($batch);
                $places += $p;
                $names += $n;

                if ($progress) {
                    $progress($lines, $places, $names);
                }
            }
        } finally {
            fclose($handle);
        }

        return ['lines' => $lines, 'places' => $places, 'names' => $names];
    }

    /**
     * @return array{place: array<string, mixed>, names: list<string>}|null
     */
    private function parseLine(string $line): ?array
    {
        $cols = explode("\t", $line);

        if (count($cols) < 15) {
            return null;
        }

        $geonamesId = (int) $cols[0];
        $name = trim($cols[1]);
        $asciiName = trim($cols[2]);
        $lat = (float) $cols[4];
        $lng = (float) $cols[5];
        $countryCode = strtoupper(trim($cols[8]));

        if ($geonamesId <= 0 || $name === '' || strlen($countryCode) !== 2) {
            return null;
        }

        $searchNames = [];

        foreach ([$name, $asciiName, ...explode(',', $cols[3])] as $candidate) {
            $normalized = PlaceResolverService::normalize($candidate);

            if ($this->isSearchableName($normalized)) {
                $searchNames[$normalized] = true;
            }
        }

        if ($searchNames === []) {
            return null;
        }

        return [
            'place' => [
                'geonames_id' => $geonamesId,
                'name' => $name,
                'ascii_name' => $asciiName !== '' ? $asciiName : $name,
                'lat' => $lat,
                'lng' => $lng,
                'country_code' => $countryCode,
                'population' => max(0, (int) $cols[14]),
            ],
            'names' => array_keys($searchNames),
        ];
    }

    private function isSearchableName(string $normalized): bool
    {
        return $normalized !== ''
            && strlen($normalized) <= 100
            && preg_match("/^[a-z0-9][a-z0-9 .'-]*$/", $normalized) === 1;
    }

    /**
     * @param  list<array{place: array<string, mixed>, names: list<string>}>  $batch
     * @return array{int, int}
     */
    private function flush(array $batch): array
    {
        DB::table('geo_places')->upsert(
            array_column($batch, 'place'),
            ['geonames_id'],
            ['name', 'ascii_name', 'lat', 'lng', 'country_code', 'population'],
        );

        $idsByGeonamesId = GeoPlace::query()
            ->whereIn('geonames_id', array_column(array_column($batch, 'place'), 'geonames_id'))
            ->pluck('id', 'geonames_id');

        $nameRows = [];

        foreach ($batch as $row) {
            $placeId = $idsByGeonamesId[$row['place']['geonames_id']] ?? null;

            if ($placeId === null) {
                continue;
            }

            foreach ($row['names'] as $normalized) {
                $nameRows[] = ['geo_place_id' => $placeId, 'name_normalized' => $normalized];
            }
        }

        $inserted = DB::table('geo_place_names')->insertOrIgnore($nameRows);

        return [count($batch), $inserted];
    }

    private function isUrl(string $source): bool
    {
        return str_starts_with($source, 'https://') || str_starts_with($source, 'http://');
    }

    private function download(string $url): string
    {
        $target = tempnam(sys_get_temp_dir(), 'geonames_');

        if ($target === false) {
            throw new RuntimeException('Could not create a temporary file for the download');
        }

        $response = Http::timeout(300)->withOptions(['sink' => $target])->get($url);

        if (! $response->successful()) {
            @unlink($target);
            throw new RuntimeException("Download failed with HTTP {$response->status()}: {$url}");
        }

        return $target;
    }
}
