<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Nightly Postgres dump, shipped to Cloudflare R2 and Scaleway Object Storage.
 *
 * Runs in the scheduler role, which already has DB_HOST/DB_PASSWORD injected
 * and can reach the `overlabels-postgres` accessory over the Kamal network, so
 * this needs no database credentials of its own. Retention is NOT handled here:
 * both buckets carry a 30-day lifecycle rule, which is one less thing that can
 * have a bug and delete the wrong object.
 *
 * Two providers is the "2" in 3-2-1. The dump is taken ONCE and the same file
 * is pushed to each disk under an identical key, so the two copies are
 * byte-identical rather than two dumps of a database that moved in between.
 *
 * Every disk is attempted even after one fails - see uploadAll(). A fail-fast
 * loop would mean a Cloudflare outage silently costs you the Scaleway copy too,
 * which is the exact correlated failure a second provider exists to prevent.
 *
 * @see docs/deploy/database-backups.md for the other half of this - restoring a dump.
 */
class BackupDatabase extends Command
{
    /**
     * Floor for a dump we are willing to call a backup. The schema alone is
     * already several hundred KB, so anything under this means pg_dump exited 0
     * having written essentially nothing - the silent failure that leaves you
     * with 30 days of empty files and no idea until you need one.
     */
    private const MIN_PLAUSIBLE_BYTES = 10_240;

    /** pg_dump on a ~50 MB database takes seconds; this is a hang guard. */
    private const DUMP_TIMEOUT_SECONDS = 1800;

    protected $signature = 'backup:database
                            {--disk=* : Filesystem disks to upload the dump to (repeatable; defaults to services.backups.disks)}
                            {--keep-local : Keep the dump on local disk after a successful upload}';

    protected $description = 'Dump the Postgres database and ship it to object storage';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $localPath = null;

        try {
            $disks = $this->disks();
            $localPath = $this->dump();
            $bytes = filesize($localPath);

            // One key for every destination. Derived once so the copies are
            // addressable by the same name whichever provider you reach for.
            $key = 'daily/'.basename($localPath);

            $results = $this->uploadAll($localPath, $key, $disks);
            $seconds = round(microtime(true) - $startedAt, 1);

            $failed = array_filter($results, fn (?string $error) => $error !== null);

            if ($failed !== []) {
                Log::error('Database backup FAILED', [
                    'key' => $key,
                    'bytes' => $bytes,
                    'seconds' => $seconds,
                    'results' => $results,
                ]);

                $this->error(sprintf(
                    'Backup failed on %d of %d destinations: %s',
                    count($failed),
                    count($results),
                    implode(', ', array_keys($failed)),
                ));

                $this->shout($this->summarise($failed), $results);

                return self::FAILURE;
            }

            Log::info('Database backup uploaded', [
                'key' => $key,
                'bytes' => $bytes,
                'seconds' => $seconds,
                'disks' => array_keys($results),
            ]);

            $this->info(sprintf(
                'Backed up %s to %s (key %s) in %ss.',
                $this->humanBytes($bytes),
                implode(' + ', array_keys($results)),
                $key,
                $seconds,
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Only reachable before any upload is attempted - the dump itself,
            // or a misconfigured disk list. Per-disk failures are handled above
            // so that one dead provider cannot skip the others.
            Log::error('Database backup FAILED', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Backup failed: '.$e->getMessage());

            $this->shout($e->getMessage());

            return self::FAILURE;
        } finally {
            // Always sweep the local dump, including after a failure - a
            // half-written file left behind every night would quietly fill the
            // container's disk.
            if ($localPath !== null && file_exists($localPath) && ! $this->option('keep-local')) {
                File::delete($localPath);
            }
        }
    }

    /**
     * Resolve the destinations for this run: --disk if given, else config.
     *
     * @return list<string>
     */
    private function disks(): array
    {
        /** @var list<string> $disks */
        $disks = $this->option('disk') ?: config('services.backups.disks', []);

        $disks = array_values(array_unique(array_filter(array_map('trim', $disks))));

        if ($disks === []) {
            // A run with nowhere to put the dump would otherwise exit 0 having
            // backed up nothing at all, which is the worst possible outcome:
            // a green healthcheck and no data.
            throw new RuntimeException(
                'No backup destinations configured. Set BACKUP_DISKS or pass --disk.'
            );
        }

        return $disks;
    }

    /**
     * Upload the dump to every destination, and keep going after a failure.
     *
     * Returns a disk => error map, where null means the copy landed and was
     * verified. The caller decides the exit code from that; this method never
     * throws for an upload failure, because "R2 is down" must not be allowed to
     * mean "we also skipped Scaleway".
     *
     * @param  list<string>  $disks
     * @return array<string, string|null>
     */
    private function uploadAll(string $path, string $key, array $disks): array
    {
        $results = [];

        foreach ($disks as $disk) {
            try {
                $this->upload($path, $key, $disk);
                $results[$disk] = null;
                $this->line("  <info>ok</info>    {$disk}:{$key}");
            } catch (Throwable $e) {
                $results[$disk] = $e->getMessage();
                $this->line("  <fg=red>fail</>  {$disk}: ".$e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Human-readable one-liner for the disks that failed.
     *
     * @param  array<string, string|null>  $failed
     */
    private function summarise(array $failed): string
    {
        return implode("\n", array_map(
            fn (string $disk, ?string $error) => "{$disk}: {$error}",
            array_keys($failed),
            $failed,
        ));
    }

    /**
     * Run pg_dump and return the path to the compressed dump on local disk.
     */
    private function dump(): string
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        if (($db['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException(
                "backup:database supports pgsql only, but the default connection [{$connection}] is [".($db['driver'] ?? 'unknown').'].'
            );
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.$this->filename($db['database']);

        $process = new Process(
            [
                'pg_dump',
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--username='.$db['username'],
                '--dbname='.$db['database'],
                '--format=plain',
                // pg_dump gzips the plain-SQL stream itself. Doing it here rather
                // than piping through gzip means the exit code we check is
                // pg_dump's own, not the last command in a shell pipeline.
                '--compress=9',
                // A restore almost never lands in a database owned by
                // `overlabels` - local dev runs as `postgres` - and ownership or
                // ACL statements abort the restore on the first unknown role.
                '--no-owner',
                '--no-privileges',
                '--file='.$path,
            ],
            base_path(),
            // Password through the environment, never argv: argv is readable by
            // any other process on the host via /proc.
            ['PGPASSWORD' => (string) $db['password']],
            null,
            self::DUMP_TIMEOUT_SECONDS,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('pg_dump failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        if (! file_exists($path)) {
            throw new RuntimeException("pg_dump reported success but wrote no file to {$path}.");
        }

        $bytes = filesize($path);

        if ($bytes < self::MIN_PLAUSIBLE_BYTES) {
            throw new RuntimeException(
                "pg_dump produced an implausibly small dump ({$bytes} bytes, floor is ".self::MIN_PLAUSIBLE_BYTES.' bytes).'
            );
        }

        return $path;
    }

    /**
     * Stream the dump to the given disk and verify it arrived intact.
     */
    private function upload(string $path, string $key, string $name): void
    {
        $disk = Storage::disk($name);

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Could not open {$path} for reading.");
        }

        try {
            $disk->writeStream($key, $stream);
        } finally {
            // Flysystem closes the handle itself on success; guard so we don't
            // double-close, but still clean up if writeStream threw early.
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // Read the size back rather than trusting the write. This is the whole
        // difference between "we uploaded something" and "the backup is there".
        $localBytes = filesize($path);
        $remoteBytes = $disk->size($key);

        if ($remoteBytes !== $localBytes) {
            throw new RuntimeException(
                "Uploaded object size mismatch for {$name}:{$key}: local {$localBytes} bytes, remote {$remoteBytes} bytes."
            );
        }
    }

    private function filename(string $database): string
    {
        return sprintf('%s-%s.sql.gz', $database, now()->format('Y-m-d-His'));
    }

    /**
     * Tell a human the backup broke. Best-effort by design: a failed webhook
     * must not mask the backup failure that is already being logged.
     *
     * When some destinations did land, say so explicitly. "R2 has last night's
     * dump, Scaleway does not" is a very different 03:00 than "there is no
     * backup", and the message is read at the point where that matters.
     *
     * @param  array<string, string|null>  $results  disk => error, null = landed
     */
    private function shout(string $message, array $results = []): void
    {
        $url = config('services.backups.alert_webhook');

        if (! $url) {
            Log::warning('Database backup failed but no BACKUP_ALERT_WEBHOOK_URL is configured.');

            return;
        }

        $fields = [
            ['name' => 'Host', 'value' => config('app.url'), 'inline' => true],
        ];

        if ($results !== []) {
            $landed = array_keys(array_filter($results, fn (?string $e) => $e === null));

            $fields[] = [
                'name' => 'Destinations',
                'value' => implode("\n", array_map(
                    fn (string $disk, ?string $error) => ($error === null ? ':white_check_mark: ' : ':x: ').$disk,
                    array_keys($results),
                    $results,
                )),
                'inline' => true,
            ];

            $fields[] = [
                'name' => 'Copies that landed',
                'value' => $landed === []
                    ? 'None - there is NO backup for tonight.'
                    : implode(', ', $landed),
                'inline' => false,
            ];
        }

        try {
            Http::timeout(10)->post($url, [
                'embeds' => [[
                    'title' => 'Database backup FAILED',
                    'color' => 0xDC2626, // red-600
                    'description' => Str::limit($message, 1800),
                    'fields' => $fields,
                    'timestamp' => now()->toIso8601String(),
                ]],
            ]);
        } catch (Throwable $e) {
            Log::error('Backup alert webhook failed', ['error' => $e->getMessage()]);
        }
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1_048_576
            ? round($bytes / 1_048_576, 1).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
