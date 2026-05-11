<?php

namespace App\Services;

use App\Models\CloudinaryUpload;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CloudinaryUploadService
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
     * Map of kind -> [folder, upload_preset]. Presets are pre-configured in
     * the Cloudinary dashboard with the appropriate transformations
     * (1280x720 fill crop for screenshots, etc.) - we just reference them
     * by name from the signed server-side upload.
     */
    private const KIND_CONFIG = [
        CloudinaryUpload::KIND_TEMPLATE_SCREENSHOT => [
            'folder' => 'overlays/screenshots',
            'preset' => 'overlabels-overlay-screenshots',
        ],
        CloudinaryUpload::KIND_KIT_THUMBNAIL => [
            'folder' => 'kits/thumbnails',
            'preset' => 'overlabels-kit-thumbnails',
        ],
    ];

    public function upload(UploadedFile $file, User $user, string $kind): CloudinaryUpload
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

        $cloudinary = new Cloudinary(config('services.cloudinary.url'));
        $result = $cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $config['folder'],
                'upload_preset' => $config['preset'],
                'resource_type' => 'image',
            ]
        );

        return CloudinaryUpload::create([
            'user_id' => $user->id,
            'public_id' => $result['public_id'],
            'secure_url' => $result['secure_url'],
            'kind' => $kind,
            'bytes' => $result['bytes'] ?? null,
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
            'format' => $result['format'] ?? null,
        ]);
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

        CloudinaryUpload::where('secure_url', $url)
            ->whereNull('claimed_at')
            ->update(['claimed_at' => now()]);
    }

    /**
     * Delete a Cloudinary asset by its delivery URL, but only if no other
     * persisted model still references it (forks copy screenshot_url
     * verbatim, so we have to walk OverlayTemplate and Kit before
     * destroying the asset).
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

        $publicId = $this->extractPublicId($url);
        if ($publicId === null) {
            return;
        }

        try {
            $cloudinary = new Cloudinary(config('services.cloudinary.url'));
            $cloudinary->uploadApi()->destroy($publicId);
            CloudinaryUpload::where('public_id', $publicId)->delete();
        } catch (Exception $e) {
            Log::warning('Failed to delete Cloudinary asset', [
                'url' => $url,
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract the Cloudinary public_id from a delivery URL. Handles both
     * versioned (/v123/) and unversioned URLs, and folder-prefixed
     * public_ids (which contain slashes).
     *
     * Format: https://res.cloudinary.com/{cloud}/image/upload/v{version}/{public_id}.{ext}
     */
    public function extractPublicId(string $url): ?string
    {
        if (preg_match('#/upload/(?:v\d+/)?(.+)\.\w+$#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * "Powered by Overlabels" watermark overlay public_id (300x80 transparent
     * PNG, anchored south-east on the screenshot).
     */
    private const WATERMARK_PUBLIC_ID = 'powered-by-overlabels_z4l5ne';

    /**
     * Inject a "Powered by Overlabels" watermark into a Cloudinary delivery
     * URL. Used on every public-facing surface (public preview, OG image,
     * social cards) so screenshots stay branded wherever they're shared. The
     * original URL stays in the database; the watermark only lives at the
     * delivery layer, which means owners on the edit screen still see their
     * raw upload and the watermark never bakes into the stored asset.
     *
     * Returns the input unchanged when it's null/empty or doesn't look like
     * one of our own Cloudinary delivery URLs.
     */
    public function brandedUrl(?string $url): ?string
    {
        if (! $url) {
            return $url;
        }

        if (! str_contains($url, '/image/upload/')) {
            return $url;
        }

        $transformation = 'l_'.self::WATERMARK_PUBLIC_ID.',w_200,o_90,g_south_east,x_24,y_24';

        return preg_replace(
            '#/image/upload/#',
            '/image/upload/'.$transformation.'/',
            $url,
            1
        );
    }
}
