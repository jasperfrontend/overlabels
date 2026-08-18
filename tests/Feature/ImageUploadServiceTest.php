<?php

use App\Models\ImageUpload;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

function makeOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

/**
 * The delivery base URL the `images` disk is configured with. Tests build
 * expected URLs from this rather than hardcoding images.overlabels.com, so a
 * change to the disk config doesn't need every test edited.
 */
function imagesBase(): string
{
    return rtrim((string) config('filesystems.disks.images.url'), '/');
}

test('pathForUrl resolves our own URLs and rejects everything else', function () {
    $service = new ImageUploadService;
    $base = imagesBase();

    expect($service->pathForUrl($base.'/overlays/screenshots/abc.webp'))
        ->toBe('overlays/screenshots/abc.webp')
        ->and($service->pathForUrl($base.'/kits/thumbnails/def.webp'))
        ->toBe('kits/thumbnails/def.webp')
        // A leftover Cloudinary URL must NOT resolve to a key in our bucket.
        ->and($service->pathForUrl('https://res.cloudinary.com/x/image/upload/v1/folder/abc.jpg'))
        ->toBeNull()
        ->and($service->pathForUrl('https://evil.example.com/overlays/screenshots/abc.webp'))
        ->toBeNull()
        ->and($service->pathForUrl($base.'/'))
        ->toBeNull()
        ->and($service->pathForUrl($base.'/../../etc/passwd'))
        ->toBeNull()
        ->and($service->pathForUrl($base.'/a.webp?x=b.webp'))
        ->toBeNull();
});

test('urlForPath and pathForUrl round-trip', function () {
    $service = new ImageUploadService;

    expect($service->pathForUrl($service->urlForPath('overlays/screenshots/abc.webp')))
        ->toBe('overlays/screenshots/abc.webp');
});

test('upload crops to 16:9, encodes webp and writes to the images disk', function () {
    Storage::fake('images');
    $user = makeOwner();

    $upload = (new ImageUploadService)->upload(
        UploadedFile::fake()->image('shot.png', 1920, 1920),
        $user,
        ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    );

    Storage::disk('images')->assertExists($upload->path);

    expect($upload->path)->toStartWith('overlays/screenshots/')
        ->and($upload->path)->toEndWith('.webp')
        ->and($upload->format)->toBe('webp')
        ->and($upload->url)->toBe((new ImageUploadService)->urlForPath($upload->path))
        // 1920x1920 cropped to the 1280x720 box: capped at 1280 wide, 16:9.
        ->and($upload->width)->toBe(1280)
        ->and($upload->height)->toBe(720)
        ->and($upload->bytes)->toBeGreaterThan(0)
        ->and($upload->claimed_at)->toBeNull();
});

test('upload never scales a small image up to the target box', function () {
    Storage::fake('images');
    $user = makeOwner();

    // 800x800 is comfortably over the 400x400 floor but under the 1280x720
    // target. coverDown must crop it to 16:9 without inventing pixels.
    $upload = (new ImageUploadService)->upload(
        UploadedFile::fake()->image('small.png', 800, 800),
        $user,
        ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    );

    expect($upload->width)->toBe(800)
        ->and($upload->height)->toBe(450);
});

test('kit thumbnails land in their own folder', function () {
    Storage::fake('images');
    $user = makeOwner();

    $upload = (new ImageUploadService)->upload(
        UploadedFile::fake()->image('thumb.png', 2560, 1440),
        $user,
        ImageUpload::KIND_KIT_THUMBNAIL,
    );

    expect($upload->path)->toStartWith('kits/thumbnails/')
        ->and($upload->width)->toBe(2560)
        ->and($upload->height)->toBe(1440);
});

/*
 * Pixel ceiling.
 *
 * These drive the predicate with integers rather than generating fixtures. A
 * test that actually built an 8000x5000 image would allocate the exact 160 MB
 * bitmap the ceiling exists to prevent, so the fixture would be the bug.
 */
test('exceedsPixelLimit refuses images too large to decode', function () {
    // Measured: 5200x3900 (20.3 MP, a 4.51 MB JPEG - under half the file cap)
    // exhausted a 128M memory_limit with the framework booted.
    expect(ImageUploadService::exceedsPixelLimit(8000, 5000))->toBeTrue()
        ->and(ImageUploadService::exceedsPixelLimit(10000, 8000))->toBeTrue();
});

test('exceedsPixelLimit allows an 8K screenshot, which is the largest real input', function () {
    // 7680x4320 = 33.2 MP. If this ever starts failing, the ceiling has been
    // lowered below the point of the feature.
    expect(ImageUploadService::exceedsPixelLimit(7680, 4320))->toBeFalse();
});

test('exceedsPixelLimit allows the sizes people actually upload', function () {
    expect(ImageUploadService::exceedsPixelLimit(1920, 1080))->toBeFalse()  // 1080p
        ->and(ImageUploadService::exceedsPixelLimit(2560, 1440))->toBeFalse()  // the recommended kit thumbnail
        ->and(ImageUploadService::exceedsPixelLimit(3840, 2160))->toBeFalse()  // 4K
        ->and(ImageUploadService::exceedsPixelLimit(5120, 2880))->toBeFalse(); // 5K
});

test('the pixel ceiling sits exactly on MAX_MEGAPIXELS', function () {
    $limit = ImageUploadService::MAX_MEGAPIXELS * 1_000_000;

    expect(ImageUploadService::exceedsPixelLimit($limit, 1))->toBeFalse()
        ->and(ImageUploadService::exceedsPixelLimit($limit + 1, 1))->toBeTrue();
});

test('the memory_limit that makes the ceiling safe is actually configured', function () {
    // The ceiling and the memory limit are a pair: 36 MP peaks at ~196 MB, so
    // shipping the ceiling without the 256M would restore the fatal. This
    // reads the ini we ship rather than trusting a comment.
    $ini = file_get_contents(base_path('docker/php-uploads.ini'));

    expect($ini)->toMatch('/^\s*memory_limit\s*=\s*256M\s*$/m');
});

test('claim stamps claimed_at on a matching unclaimed upload', function () {
    $user = makeOwner();
    $upload = ImageUpload::create([
        'user_id' => $user->id,
        'path' => 'overlays/screenshots/abc.webp',
        'url' => imagesBase().'/overlays/screenshots/abc.webp',
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    (new ImageUploadService)->claim($upload->url);

    expect($upload->fresh()->claimed_at)->not->toBeNull();
});

test('claim is a no-op when url is null or already claimed', function () {
    $user = makeOwner();
    $upload = ImageUpload::create([
        'user_id' => $user->id,
        'path' => 'overlays/screenshots/abc.webp',
        'url' => imagesBase().'/overlays/screenshots/abc.webp',
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
        'claimed_at' => now()->subMinutes(5),
    ]);
    $originalClaim = $upload->claimed_at;

    $service = new ImageUploadService;
    $service->claim(null);
    $service->claim($upload->url);

    expect($upload->fresh()->claimed_at->eq($originalClaim))->toBeTrue();
});

test('deleteByUrl removes the object and the row when nothing references it', function () {
    Storage::fake('images');
    $user = makeOwner();
    $path = 'overlays/screenshots/lonely.webp';
    $url = imagesBase().'/'.$path;

    Storage::disk('images')->put($path, 'bytes');
    $upload = ImageUpload::create([
        'user_id' => $user->id,
        'path' => $path,
        'url' => $url,
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    (new ImageUploadService)->deleteByUrl($url);

    Storage::disk('images')->assertMissing($path);
    expect(ImageUpload::find($upload->id))->toBeNull();
});

test('deleteByUrl skips when another template still references the URL', function () {
    Storage::fake('images');
    $user = makeOwner();
    $path = 'overlays/screenshots/shared.webp';
    $url = imagesBase().'/'.$path;

    Storage::disk('images')->put($path, 'bytes');
    OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'screenshot_url' => $url,
        'fork_of_id' => null,
    ]);
    $other = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'screenshot_url' => $url,
        'fork_of_id' => null,
    ]);
    $upload = ImageUpload::create([
        'user_id' => $user->id,
        'path' => $path,
        'url' => $url,
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    // Pretend $other was just deleted - exclude it from the reference check.
    (new ImageUploadService)->deleteByUrl($url, excludeTemplateId: $other->id);

    Storage::disk('images')->assertExists($path);
    expect(ImageUpload::find($upload->id))->not->toBeNull();
});

test('deleteByUrl skips when a kit still references the URL', function () {
    Storage::fake('images');
    $user = makeOwner();
    $path = 'kits/thumbnails/kit-thumb.webp';
    $url = imagesBase().'/'.$path;

    Storage::disk('images')->put($path, 'bytes');
    Kit::create([
        'owner_id' => $user->id,
        'title' => 'Test',
        'description' => 'd',
        'is_public' => true,
        'thumbnail' => $url,
    ]);
    $upload = ImageUpload::create([
        'user_id' => $user->id,
        'path' => $path,
        'url' => $url,
        'kind' => ImageUpload::KIND_KIT_THUMBNAIL,
    ]);

    (new ImageUploadService)->deleteByUrl($url);

    Storage::disk('images')->assertExists($path);
    expect(ImageUpload::find($upload->id))->not->toBeNull();
});

test('deleteByUrl leaves a legacy Cloudinary URL alone', function () {
    Storage::fake('images');
    $user = makeOwner();
    $url = 'https://res.cloudinary.com/x/image/upload/v1/overlays/screenshots/old.jpg';

    $upload = ImageUpload::create([
        'user_id' => $user->id,
        'path' => 'overlays/screenshots/old',
        'url' => $url,
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    (new ImageUploadService)->deleteByUrl($url);

    // Nothing to delete on our disk, and the row must survive so the
    // migrate command can still find and move it.
    expect(ImageUpload::find($upload->id))->not->toBeNull();
});

test('upload endpoint surfaces the dimension validation message verbatim', function () {
    Storage::fake('images');
    $user = makeOwner();

    // 100x100 is below the 400x400 minimum - the service throws a
    // ValidationException; the controller must let it bubble so Laravel
    // renders 422 with the actual message instead of a generic 500.
    $response = $this->actingAs($user)->postJson('/images/upload', [
        'image' => UploadedFile::fake()->image('tiny.jpg', 100, 100),
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.image.0'))
        ->toContain('400x400');
});

test('upload endpoint returns the delivery URL on success', function () {
    Storage::fake('images');
    $user = makeOwner();

    $response = $this->actingAs($user)->postJson('/images/upload', [
        'image' => UploadedFile::fake()->image('shot.png', 1600, 900),
        'kind' => ImageUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    $response->assertOk();
    expect($response->json('url'))->toStartWith(imagesBase().'/overlays/screenshots/');
});
