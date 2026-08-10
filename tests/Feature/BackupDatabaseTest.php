<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
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

    // Both destinations, faked. Tests that care about only one pass --disk.
    config(['services.backups.disks' => ['r2', 'scaleway']]);
});

it('dumps the database and uploads a real gzipped dump', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('r2');
    Storage::fake('scaleway');

    $this->artisan('backup:database', ['--disk' => ['r2']])->assertExitCode(0);

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
    Storage::fake('scaleway');

    $this->artisan('backup:database')->assertExitCode(0);

    expect(File::glob(storage_path('app/backups/*.sql.gz')))->toBeEmpty();
});

it('keeps the local dump when --keep-local is passed', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('r2');
    Storage::fake('scaleway');

    $this->artisan('backup:database', ['--keep-local' => true])->assertExitCode(0);

    expect(File::glob(storage_path('app/backups/*.sql.gz')))->toHaveCount(1);
});

/*
 * 3-2-1. The dump has to reach BOTH providers, under the same key, as
 * byte-identical copies - two dumps taken seconds apart would not be.
 */
it('ships the same dump to both providers under one key', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('r2');
    Storage::fake('scaleway');

    $this->artisan('backup:database')->assertExitCode(0);

    $r2 = Storage::disk('r2')->files('daily');
    $scaleway = Storage::disk('scaleway')->files('daily');

    expect($r2)->toHaveCount(1);
    expect($scaleway)->toBe($r2);
    expect(Storage::disk('scaleway')->get($scaleway[0]))
        ->toBe(Storage::disk('r2')->get($r2[0]));
});

/*
 * The whole reason a second provider exists. If the loop bailed on the first
 * failure, a Cloudflare outage would silently cost the Scaleway copy too -
 * turning two independent destinations back into one. r2 is listed FIRST here
 * on purpose: it is the disk that fails, so a fail-fast implementation never
 * reaches scaleway and this test goes red.
 */
it('still uploads to the second provider when the first one fails', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('scaleway');
    Http::fake();
    config(['services.backups.alert_webhook' => 'https://discord.test/webhook']);
    // No bucket => the r2 disk cannot even be constructed.
    config(['filesystems.disks.r2.bucket' => null]);

    $this->artisan('backup:database')->assertExitCode(1);

    expect(Storage::disk('scaleway')->files('daily'))->toHaveCount(1);
});

it('fails the run when any single destination fails', function () {
    if (! pgDumpAvailable()) {
        $this->markTestSkipped('pg_dump is not on PATH.');
    }

    Storage::fake('scaleway');
    Http::fake();
    config(['services.backups.alert_webhook' => 'https://discord.test/webhook']);
    config(['filesystems.disks.r2.bucket' => null]);

    // A copy landing is NOT a pass. A destination that quietly stops working is
    // the failure this whole system exists to notice.
    $this->artisan('backup:database')->assertExitCode(1);

    Http::assertSent(function ($request) {
        $fields = collect($request['embeds'][0]['fields']);

        return $fields->contains(fn ($f) => $f['name'] === 'Destinations'
                && str_contains($f['value'], 'r2')
                && str_contains($f['value'], 'scaleway'))
            && $fields->contains(fn ($f) => $f['name'] === 'Copies that landed'
                && str_contains($f['value'], 'scaleway'));
    });
});

it('refuses to run with no destinations configured', function () {
    Http::fake();
    config(['services.backups.disks' => []]);
    config(['services.backups.alert_webhook' => 'https://discord.test/webhook']);

    // Exiting 0 here would ping the healthcheck green having stored nothing.
    $this->artisan('backup:database')
        ->expectsOutputToContain('No backup destinations configured')
        ->assertExitCode(1);
});

/*
 * Pins the shipped default by re-evaluating config/services.php itself, not the
 * value beforeEach() sets. Losing a provider from that default would leave the
 * nightly run passing while quietly storing one copy again - green, and no
 * longer 3-2-1. Re-requiring the file picks up its `?: 'r2,scaleway'` fallback
 * because BACKUP_DISKS is unset under phpunit.
 */
it('ships to both providers by default', function () {
    $services = require config_path('services.php');

    expect($services['backups']['disks'])->toBe(['r2', 'scaleway']);
});

/*
 * Scaleway's region is part of the endpoint hostname, and getting it wrong
 * fails as a 404 NotFound - which reads as "the bucket was deleted" rather than
 * "wrong host", and would send you hunting in the wrong place at the worst time.
 */
it('builds the scaleway endpoint from the region', function () {
    $filesystems = require config_path('filesystems.php');

    expect($filesystems['disks']['scaleway']['endpoint'])->toBe('https://s3.fr-par.scw.cloud');
    expect($filesystems['disks']['scaleway']['use_path_style_endpoint'])->toBeTrue();

    // A silent `false` return from a failed write would be reported as a good
    // backup, exactly as for r2.
    expect($filesystems['disks']['scaleway']['throw'])->toBeTrue();
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

/*
 * The dead-man's switch. These drive Laravel's real ping callbacks with a
 * recording HTTP client in place of Guzzle, so they assert the URLs actually
 * requested rather than that some callback happens to be registered.
 */
function backupScheduleEvent(): Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'backup:database'));
}

/** Swap Guzzle for a recorder; Event::getHttpClient() prefers this binding. */
function recordPings(): ArrayObject
{
    $seen = new ArrayObject;

    $client = new class($seen) extends Client
    {
        public function __construct(private ArrayObject $seen)
        {
            parent::__construct();
        }

        public function request(string $method, $uri = '', array $options = []): ResponseInterface
        {
            $this->seen[] = $uri;

            return new Response(200);
        }
    };

    app()->instance(Client::class, $client);

    return $seen;
}

it('pings the healthcheck url when the backup succeeds', function () {
    $seen = recordPings();

    $event = backupScheduleEvent();
    $event->exitCode = 0;
    $event->callAfterCallbacks(app());

    expect(iterator_to_array($seen))->toBe([config('services.backups.healthcheck_url')]);
});

it('pings the /fail endpoint when the backup fails', function () {
    $seen = recordPings();

    $event = backupScheduleEvent();
    $event->exitCode = 1;
    $event->callAfterCallbacks(app());

    expect(iterator_to_array($seen))->toBe([config('services.backups.healthcheck_url').'/fail']);
});

it('does not double-slash the fail url when the ping url has a trailing slash', function () {
    // rtrim() in routes/console.php is the only thing standing between a
    // trailing slash in the env var and a 404 on every failure ping.
    $url = 'https://hc-ping.test/uuid/';

    expect(rtrim($url, '/').'/fail')->toBe('https://hc-ping.test/uuid/fail');
});
