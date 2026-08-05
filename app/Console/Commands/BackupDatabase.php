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
 * Nightly Postgres dump, shipped to Cloudflare R2.
 *
 * Runs in the scheduler role, which already has DB_HOST/DB_PASSWORD injected
 * and can reach the `overlabels-postgres` accessory over the Kamal network, so
 * this needs no database credentials of its own. Retention is NOT handled here:
 * the R2 bucket carries a 30-day lifecycle rule, which is one less thing that
 * can have a bug and delete the wrong object.
 *
 * @see docs/deploy/restore.md for the other half of this - restoring a dump.
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
                            {--disk=r2 : Filesystem disk to upload the dump to}
                            {--keep-local : Keep the dump on local disk after a successful upload}';

    protected $description = 'Dump the Postgres database and ship it to object storage';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $localPath = null;

        try {
            $localPath = $this->dump();
            $bytes = filesize($localPath);
            $key = $this->upload($localPath, (string) $this->option('disk'));

            $seconds = round(microtime(true) - $startedAt, 1);

            Log::info('Database backup uploaded', [
                'key' => $key,
                'bytes' => $bytes,
                'seconds' => $seconds,
            ]);

            $this->info(sprintf(
                'Backed up %s to %s:%s in %ss.',
                $this->humanBytes($bytes),
                $this->option('disk'),
                $key,
                $seconds,
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
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
     *
     * @return string the object key it was written to
     */
    private function upload(string $path, string $disk): string
    {
        $key = 'daily/'.basename($path);
        $disk = Storage::disk($disk);

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
                "Uploaded object size mismatch for {$key}: local {$localBytes} bytes, remote {$remoteBytes} bytes."
            );
        }

        return $key;
    }

    private function filename(string $database): string
    {
        return sprintf('%s-%s.sql.gz', $database, now()->format('Y-m-d-His'));
    }

    /**
     * Tell a human the backup broke. Best-effort by design: a failed webhook
     * must not mask the backup failure that is already being logged.
     */
    private function shout(string $message): void
    {
        $url = config('services.backups.alert_webhook');

        if (! $url) {
            Log::warning('Database backup failed but no BACKUP_ALERT_WEBHOOK_URL is configured.');

            return;
        }

        try {
            Http::timeout(10)->post($url, [
                'embeds' => [[
                    'title' => 'Database backup FAILED',
                    'color' => 0xDC2626, // red-600
                    'description' => Str::limit($message, 1800),
                    'fields' => [
                        ['name' => 'Host', 'value' => config('app.url'), 'inline' => true],
                        ['name' => 'Disk', 'value' => (string) $this->option('disk'), 'inline' => true],
                    ],
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
