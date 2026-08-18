<?php

namespace App\Console\Commands;

use App\Models\ImageUpload;
use App\Services\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One-shot move of every Cloudinary-hosted image onto the R2 `images` disk.
 *
 * It pulls the bytes from the LIVE Cloudinary URLs rather than from the
 * archive zip in docs/private, deliberately. That export is a flat directory
 * of sanitised filenames - the folder prefix is stripped and name collisions
 * were resolved with `-1` suffixes - so there is no reliable mapping back to
 * the public_id a database row actually references. The delivery URLs in the
 * database are unambiguous by definition, so we resolve against those and
 * keep the zip as the fallback if Cloudinary is gone before this runs.
 *
 * Safe to re-run: rows already pointing at the images disk are skipped, so an
 * interrupted run resumes rather than duplicating objects.
 */
class MigrateImagesFromCloudinary extends Command
{
    protected $signature = 'images:migrate-from-cloudinary
                            {--dry-run : Report what would move without downloading, uploading or writing}';

    protected $description = 'Copy every Cloudinary-hosted screenshot and kit thumbnail onto the R2 images disk';

    public function handle(ImageUploadService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $targets = $this->collectTargets();

        if ($targets === []) {
            $this->info('No Cloudinary URLs left in the database. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d distinct Cloudinary URL(s).',
            $dryRun ? 'Would migrate' : 'Migrating',
            count($targets),
        ));

        $migrated = 0;
        $failed = 0;

        foreach ($targets as $url => $kind) {
            if ($dryRun) {
                $this->line("  [dry-run] {$kind}  {$url}");
                $migrated++;

                continue;
            }

            try {
                $response = Http::timeout(30)->get($url);

                if (! $response->successful()) {
                    $this->error("  HTTP {$response->status()}  {$url}");
                    $failed++;

                    continue;
                }

                $stored = $service->store($response->body(), $kind);
                $this->rewriteReferences($url, $stored);

                $this->line("  ok  {$url}  ->  {$stored['url']}");
                $migrated++;
            } catch (Throwable $e) {
                $this->error("  failed  {$url}  ({$e->getMessage()})");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Migrated: {$migrated}   Failed: {$failed}");

        if ($failed > 0) {
            $this->warn(
                'Rows for failed URLs still point at Cloudinary. Re-run to retry them; '
                .'anything that still fails needs restoring from '
                .'docs/private/Cloudinary_Archive_2026-08-18_20_18_67_Originals.zip by hand.'
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Every distinct Cloudinary URL still referenced anywhere, mapped to the
     * upload kind it should be re-encoded as.
     *
     * Templates and kits are read directly rather than going through
     * image_uploads because uploads predating that table (it arrived in April
     * 2026) have no row there at all, and those images are exactly as much
     * in need of moving as the tracked ones.
     *
     * @return array<string, string> url => kind
     */
    private function collectTargets(): array
    {
        $targets = [];

        $screenshots = DB::table('overlay_templates')
            ->whereNotNull('screenshot_url')
            ->where('screenshot_url', 'like', '%res.cloudinary.com%')
            ->distinct()
            ->pluck('screenshot_url');

        foreach ($screenshots as $url) {
            $targets[$url] = ImageUpload::KIND_TEMPLATE_SCREENSHOT;
        }

        $thumbnails = DB::table('kits')
            ->whereNotNull('thumbnail')
            ->where('thumbnail', 'like', '%res.cloudinary.com%')
            ->distinct()
            ->pluck('thumbnail');

        foreach ($thumbnails as $url) {
            $targets[$url] = ImageUpload::KIND_KIT_THUMBNAIL;
        }

        // Unclaimed or orphaned rows in image_uploads that nothing references
        // yet. Their own `kind` column is authoritative here.
        $tracked = DB::table('image_uploads')
            ->where('url', 'like', '%res.cloudinary.com%')
            ->get(['url', 'kind']);

        foreach ($tracked as $row) {
            $targets[$row->url] ??= $row->kind;
        }

        return $targets;
    }

    /**
     * Point every reference to $oldUrl at the freshly stored object.
     *
     * One transaction per URL rather than one for the whole run: a run that
     * dies halfway should leave the URLs it already moved consistently
     * rewritten, not roll back work whose bytes are already in R2.
     *
     * @param  array{path: string, url: string, bytes: int, width: int, height: int}  $stored
     */
    private function rewriteReferences(string $oldUrl, array $stored): void
    {
        DB::transaction(function () use ($oldUrl, $stored) {
            DB::table('overlay_templates')
                ->where('screenshot_url', $oldUrl)
                ->update(['screenshot_url' => $stored['url']]);

            DB::table('kits')
                ->where('thumbnail', $oldUrl)
                ->update(['thumbnail' => $stored['url']]);

            DB::table('image_uploads')
                ->where('url', $oldUrl)
                ->update([
                    'path' => $stored['path'],
                    'url' => $stored['url'],
                    'bytes' => $stored['bytes'],
                    'width' => $stored['width'],
                    'height' => $stored['height'],
                    'format' => 'webp',
                    'updated_at' => now(),
                ]);
        });
    }
}
