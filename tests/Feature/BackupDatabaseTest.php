<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * `pg_dump` is present on the CI runner and on any box with the Postgres client
 * tools installed, but not everywhere a developer might run the suite. The
 * end-to-end tests skip rather than fail when it is missing; the failure-path
 * tests below run everywhere, because a missing binary is itself a failure the
 * command has to handle.
 */
function pgDumpAvailable(): bool
{
    try {
        $probe = new Process(['pg_dump', '--version']);
        $probe->run();

        return $probe->isSuccessful();
    } catch (Throwable) {
        return false;
    }
}

beforeEach(function () {
    File::deleteDirectory(storage_path('app/backups'));
});

it('dumps the database and uploads a real gzipped dump', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('r2');

    $this->artisan('backup:database')->assertExitCode(0);

    $files = Storage::disk('r2')->files('daily');
    expect($files)->toHaveCount(1);

    $key = $files[0];
    expect($key)->toMatch('#^daily/[a-z0-9_]+-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$#');

    $contents = Storage::disk('r2')->get($key);

    // Gzip magic number - proves we shipped the compressed artefact and not,
    // say, an error message pg_dump wrote to the file.
    expect(substr($contents, 0, 2))->toBe("\x1f\x8b");

    // And it decompresses to an actual dump, which is the only assertion that
    // distinguishes a backup from a file of the right shape.
    $sql = gzdecode($contents);
    expect($sql)->toContain('PostgreSQL database dump');
    expect($sql)->toContain('CREATE TABLE');
});

it('deletes the local dump after a successful upload', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('r2');

    $this->artisan('backup:database')->assertExitCode(0);

    expect(File::glob(storage_path('app/backups/*.sql.gz')))->toBeEmpty();
});

it('keeps the local dump when --keep-local is passed', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('r2');

    $this->artisan('backup:database', ['--keep-local' => true])->assertExitCode(0);

    expect(File::glob(storage_path('app/backups/*.sql.gz')))->toHaveCount(1);
});

it('fails and shouts to Discord when pg_dump cannot reach the database', function () {
    Storage::fake('r2');
    Http::fake();
    config(['services.backups.alert_webhook' => 'https://discord.test/webhook']);

    // Port 1 is never a Postgres server, so pg_dump exits non-zero. If pg_dump
    // is absent entirely the process still fails, which is the same code path.
    config(['database.connections.pgsql.port' => 1]);

    $this->artisan('backup:database')->assertExitCode(1);

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/webhook'
        && str_contains($request['embeds'][0]['title'], 'FAILED'));
});

it('leaves nothing on local disk when the dump fails', function () {
    Storage::fake('r2');
    Http::fake();
    config(['database.connections.pgsql.port' => 1]);

    $this->artisan('backup:database')->assertExitCode(1);

    expect(File::glob(storage_path('app/backups/*.sql.gz')))->toBeEmpty();
});

it('does not upload anything when the dump fails', function () {
    Storage::fake('r2');
    Http::fake();
    config(['database.connections.pgsql.port' => 1]);

    $this->artisan('backup:database')->assertExitCode(1);

    expect(Storage::disk('r2')->files('daily'))->toBeEmpty();
});

it('logs instead of shouting when no alert webhook is configured', function () {
    Storage::fake('r2');
    Http::fake();
    config(['services.backups.alert_webhook' => null]);
    config(['database.connections.pgsql.port' => 1]);

    $this->artisan('backup:database')->assertExitCode(1);

    Http::assertNothingSent();
});

it('refuses to run against a non-postgres connection', function () {
    Storage::fake('r2');
    Http::fake();
    config(['services.backups.alert_webhook' => 'https://discord.test/webhook']);
    config(['database.default' => 'sqlite']);

    $this->artisan('backup:database')
        ->expectsOutputToContain('supports pgsql only')
        ->assertExitCode(1);
});

/*
 * The schedule entry is the part that makes this a backup system rather than a
 * command nobody runs, and 03:00 UTC was chosen because it is the quietest the
 * box gets. Pin both so a stray edit to routes/console.php is caught here.
 */
it('is scheduled nightly at 03:00 UTC', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'backup:database'));

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('0 3 * * *');
    expect(config('app.timezone'))->toBe('UTC');
});
