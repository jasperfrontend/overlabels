<?php

namespace App\Services;

use App\Models\ImageUpload;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    /**
     * Minimum image dimensions accepted by the upload endpoint. Anything
     * smaller is almost certainly an abuse attempt (10x10 favicon as image
     * hosting); a real overlay screenshot or kit thumbnail will easily
     * clear this bar.
     */
    public const int MIN_WIDTH = 400;

    public const int MIN_HEIGHT = 400;

    /**
     * Maximum total pixels, in megapixels.
     *
     * A FILE size limit does not bound decoding cost, and that gap is not
     * theoretical: GD holds an uncompressed bitmap of width * height * 4
     * bytes regardless of how well the source compressed, so a 4.51 MB JPEG
     * at 5200x3900 needed ~150 MB and killed the request with "Allowed memory
     * size exhausted" - under half the 10 MB file cap.
     *
     * 36 MP is chosen to clear an 8K screenshot (7680x4320 = 33.2 MP), which
     * is the largest input anyone could legitimately be capturing. Measured
     * with the framework booted, 36.2 MP peaks at 196 MB, leaving ~60 MB under
     * the 256M memory_limit set in docker/php-uploads.ini.
     *
     * THAT PAIRING IS LOAD-BEARING. Raising this without raising memory_limit
     * puts the fatal straight back.
     */
    public const int MAX_MEGAPIXELS = 36;

    /**
     * The disk everything here reads and writes. Named rather than inlined
     * so the migrate-from-Cloudinary command and the tests agree with the
     * service about where images live.
     */
    public const string DISK = 'images';

    /**
     * WebP quality. 82 is the knee of the curve for screenshots of UI: no
     * visible artefacts on text or flat colour, roughly a third the bytes
     * of the equivalent PNG.
     */
    private const int QUALITY = 82;

    /**
     * Map of kind -> [folder, max width, max height]. This replaces the
     * Cloudinary named upload presets, which did the same crop server-side
     * in their dashboard. Both surfaces render into a 16:9 box, so both
     * crop to 16:9 - see kits/create.vue and TemplateScreenshot.vue.
     */
    private const KIND_CONFIG = [
        ImageUpload::KIND_TEMPLATE_SCREENSHOT => [
            'folder' => 'overlays/screenshots',
            'width' => 1280,
            'height' => 720,
        ],
        ImageUpload::KIND_KIT_THUMBNAIL => [
            'folder' => 'kits/thumbnails',
            'width' => 2560,
            'height' => 1440,
        ],
    ];

    public function upload(UploadedFile $file, User $user, string $kind): ImageUpload
    {
        $config = self::KIND_CONFIG[$kind] ?? null;
        if ($config === null) {
            throw ValidationException::withMessages([
                'kind' => "Unknown upload kind: {$kind}",
            ]);
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw ValidationException::withMessages([
                'image' => 'File is not a readable image.',
            ]);
        }
        [$width, $height] = $info;
        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            throw ValidationException::withMessages([
                'image' => 'Image must be at least '.self::MIN_WIDTH.'x'.self::MIN_HEIGHT.'px.',
            ]);
        }

        // Checked here, against the header getimagesize() already read, so an
        // image too big to decode is refused BEFORE anything tries to decode
        // it. Doing this after handing the path to store() would be the same
        // fatal it exists to prevent.
        if (self::exceedsPixelLimit($width, $height)) {
            throw ValidationException::withMessages([
                'image' => sprintf(
                    'Image is too large to process at %dx%dpx (%.1f megapixels). The limit is %d megapixels.',
                    $width,
                    $height,
                    ($width * $height) / 1_000_000,
                    self::MAX_MEGAPIXELS,
                ),
            ]);
        }

        $stored = $this->store($file->getRealPath(), $kind);

        return ImageUpload::create([
            'user_id' => $user->id,
            'path' => $stored['path'],
            'url' => $stored['url'],
            'kind' => $kind,
            'bytes' => $stored['bytes'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'format' => 'webp',
        ]);
    }

    /**
     * Crop, encode and write one image to the images disk, returning the
     * stored object's metadata. Does NOT touch the database - `upload()` is
     * what records the row.
     *
     * `$source` is anything Intervention's `decode()` accepts: a local path
     * or the raw binary body. Keeping it that wide is deliberate - it lets a
     * caller holding bytes already in memory (a fetched response, a generated
     * image) store them without a temp file round trip.
     *
     * `coverDown` rather than `cover`: it crops to the target ratio but never
     * scales a small image UP to the target box, which would just spend bytes
     * blurring someone's 640x360 screenshot into a 1280x720 one.
     *
     * @return array{path: string, url: string, bytes: int, width: int, height: int}
     *
     * @throws Exception when the write fails
     */
    public function store(string $source, string $kind): array
    {
        $config = self::KIND_CONFIG[$kind] ?? throw new Exception("Unknown upload kind: {$kind}");

        $image = (new ImageManager(new Driver))->decode($source);
        $image->coverDown($config['width'], $config['height']);

        // strip: true drops EXIF and friends. Bytes are the small win; the real
        // one is that a phone screenshot or a camera capture stops carrying its
        // GPS tag into a world-readable bucket.
        $body = (string) $image->encode(new WebpEncoder(quality: self::QUALITY, strip: true));

        $path = $config['folder'].'/'.Str::ulid()->toBase32().'.webp';

        $written = Storage::disk(self::DISK)->put($path, $body, [
            'ContentType' => 'image/webp',
            // R2 objects are immutable once written - the path carries a ULID,
            // so a replaced image is a new key. Let the Cloudflare edge and the
            // browser hold it for a year rather than revalidating every load in
            // an OBS source that reloads on every scene change.
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        if ($written === false) {
            throw new Exception("Failed to write {$path} to the images disk.");
        }

        return [
            'path' => $path,
            'url' => $this->urlForPath($path),
            'bytes' => strlen($body),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Whether an image of these dimensions is too big to decode safely.
     *
     * Split out as its own predicate so it can be tested against arbitrary
     * dimensions without generating the fixture: a test that wanted to prove
     * 8000x5000 is refused would otherwise have to allocate the very 160 MB
     * bitmap this check exists to avoid.
     */
    public static function exceedsPixelLimit(int $width, int $height): bool
    {
        return $width * $height > self::MAX_MEGAPIXELS * 1_000_000;
    }

    /**
     * Mark an uploaded asset as claimed (referenced by a persisted template
     * or kit). Unclaimed uploads older than the sweep threshold get
     * auto-deleted; claimed ones stay until the referencing model is
     * deleted via deleteByUrl.
     */
    public function claim(?string $url): void
    {
        if (! $url) {
            return;
        }

        ImageUpload::where('url', $url)
            ->whereNull('claimed_at')
            ->update(['claimed_at' => now()]);
    }

    /**
     * Delete a stored image by its delivery URL, but only if no other
     * persisted model still references it (forks copy screenshot_url
     * verbatim, so we have to walk OverlayTemplate and Kit before
     * destroying the object).
     */
    public function deleteByUrl(?string $url, ?int $excludeTemplateId = null, ?int $excludeKitId = null): void
    {
        if (! $url) {
            return;
        }

        $templateRefs = OverlayTemplate::where('screenshot_url', $url);
        if ($excludeTemplateId !== null) {
            $templateRefs->where('id', '!=', $excludeTemplateId);
        }
        if ($templateRefs->exists()) {
            return;
        }

        $kitRefs = Kit::where('thumbnail', $url);
        if ($excludeKitId !== null) {
            $kitRefs->where('id', '!=', $excludeKitId);
        }
        if ($kitRefs->exists()) {
            return;
        }

        $path = $this->pathForUrl($url);
        if ($path === null) {
            return;
        }

        try {
            Storage::disk(self::DISK)->delete($path);
            ImageUpload::where('path', $path)->delete();
        } catch (Exception $e) {
            Log::warning('Failed to delete stored image', [
                'url' => $url,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Public delivery URL for an object key on the images disk.
     */
    public function urlForPath(string $path): string
    {
        return rtrim((string) config('filesystems.disks.'.self::DISK.'.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Inverse of urlForPath: the object key a delivery URL points at, or null
     * if the URL isn't one of ours.
     *
     * Returning null for foreign URLs is what makes deleteByUrl safe to call
     * on a row that still holds a legacy Cloudinary URL - we must never try to
     * resolve one of those to a key in our own bucket and delete whatever
     * happens to collide.
     */
    public function pathForUrl(string $url): ?string
    {
        $base = rtrim((string) config('filesystems.disks.'.self::DISK.'.url'), '/').'/';

        if (! str_starts_with($url, $base)) {
            return null;
        }

        $path = substr($url, strlen($base));

        // Reject anything that isn't a plain forward-slashed key: no traversal,
        // no query string smuggling a different object in.
        if ($path === '' || str_contains($path, '..') || str_contains($path, '?')) {
            return null;
        }

        return $path;
    }
}
