<?php

namespace App\Console\Commands;

use App\Services\Geo\GeoNamesImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Imports the GeoNames cities500 dump into the local gazetteer that backs
 * !checkin place resolution. Idempotent - re-run any time to refresh. The
 * dataset is CC-BY 4.0; attribution lives on the checkin help and settings
 * pages, not here.
 */
class ImportGeoNames extends Command
{
    protected $signature = 'geo:import
        {--source= : Local .zip path or URL (defaults to the GeoNames cities500 download)}';

    protected $description = 'Import the GeoNames cities500 gazetteer into geo_places';

    public function handle(GeoNamesImporter $importer): int
    {
        $source = $this->option('source') ?: GeoNamesImporter::DEFAULT_URL;
        $this->line("Importing from {$source} ...");

        try {
            $result = $importer->import(
                $this->option('source') ?: null,
                function (int $lines, int $places, int $names): void {
                    $this->output->write(sprintf(
                        "\r  lines read: %d | places upserted: %d | names inserted: %d",
                        $lines,
                        $places,
                        $names,
                    ));
                },
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. %d lines read, %d places upserted, %d new names inserted.',
            $result['lines'],
            $result['places'],
            $result['names'],
        ));

        return self::SUCCESS;
    }
}
