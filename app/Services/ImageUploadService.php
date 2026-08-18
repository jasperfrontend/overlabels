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
