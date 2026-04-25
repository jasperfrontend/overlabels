<?php

use App\Models\CloudinaryUpload;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\CloudinaryUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

test('extractPublicId handles versioned and unversioned URLs', function () {
    $service = new CloudinaryUploadService();

    expect($service->extractPublicId('https://res.cloudinary.com/x/image/upload/v1234/folder/sub/abc.jpg'))
        ->toBe('folder/sub/abc')
        ->and($service->extractPublicId('https://res.cloudinary.com/x/image/upload/folder/abc.png'))
        ->toBe('folder/abc')
        ->and($service->extractPublicId('https://example.com/not-cloudinary'))
        ->toBeNull();
});

test('claim stamps claimed_at on a matching unclaimed upload', function () {
    $user = makeOwner();
    $upload = CloudinaryUpload::create([
        'user_id' => $user->id,
        'public_id' => 'folder/abc',
        'secure_url' => 'https://res.cloudinary.com/x/image/upload/v1/folder/abc.jpg',
        'kind' => CloudinaryUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    (new CloudinaryUploadService())->claim($upload->secure_url);

    expect($upload->fresh()->claimed_at)->not->toBeNull();
});

test('claim is a no-op when url is null or already claimed', function () {
    $user = makeOwner();
    $upload = CloudinaryUpload::create([
        'user_id' => $user->id,
        'public_id' => 'folder/abc',
        'secure_url' => 'https://res.cloudinary.com/x/image/upload/v1/folder/abc.jpg',
        'kind' => CloudinaryUpload::KIND_TEMPLATE_SCREENSHOT,
        'claimed_at' => now()->subMinutes(5),
    ]);
    $originalClaim = $upload->claimed_at;

    $service = new CloudinaryUploadService();
    $service->claim(null);
    $service->claim($upload->secure_url);

    expect($upload->fresh()->claimed_at->eq($originalClaim))->toBeTrue();
});

test('deleteByUrl skips when another template still references the URL', function () {
    $user = makeOwner();
    $url = 'https://res.cloudinary.com/x/image/upload/v1/folder/shared.jpg';

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
    $upload = CloudinaryUpload::create([
        'user_id' => $user->id,
        'public_id' => 'folder/shared',
        'secure_url' => $url,
        'kind' => CloudinaryUpload::KIND_TEMPLATE_SCREENSHOT,
    ]);

    // Pretend $other was just deleted - exclude it from the reference check.
    (new CloudinaryUploadService())->deleteByUrl($url, excludeTemplateId: $other->id);

    expect(CloudinaryUpload::find($upload->id))->not->toBeNull();
});

test('deleteByUrl skips when a kit still references the URL', function () {
    $user = makeOwner();
    $url = 'https://res.cloudinary.com/x/image/upload/v1/folder/kit-thumb.jpg';

    Kit::create([
        'owner_id' => $user->id,
        'title' => 'Test',
        'description' => 'd',
        'is_public' => true,
        'thumbnail' => $url,
    ]);
    $upload = CloudinaryUpload::create([
        'user_id' => $user->id,
        'public_id' => 'folder/kit-thumb',
        'secure_url' => $url,
        'kind' => CloudinaryUpload::KIND_KIT_THUMBNAIL,
    ]);

    (new CloudinaryUploadService())->deleteByUrl($url);

    expect(CloudinaryUpload::find($upload->id))->not->toBeNull();
});
