<?php

use App\Models\AdminAuditLog;
use App\Models\ImageUpload;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\UserDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

// Deleting an account used to leave a trail: screenshots resolving publicly in
// the images bucket forever, a live Twitch grant still listed on the user's
// connections page, EventSub subscriptions registered against a callback that
// no longer answered, event rows detached-but-present, and session rows holding
// an IP address that no cascade reached.

beforeEach(function () {
    Http::fake([
        'id.twitch.tv/oauth2/revoke' => Http::response('', 200),
        '*' => Http::response(['data' => []], 200),
    ]);

    // Without this the erasure's image cleanup reaches for the real R2 bucket
    // and sits on a network timeout. A test must never be pointed at that
    // bucket, whatever the key it would ask for.
    Storage::fake('images');
});

it('revokes the twitch grant', function () {
    $user = User::factory()->create(['access_token' => 'a-live-token']);

    app(UserDeletionService::class)->eraseAccount($user);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'id.twitch.tv/oauth2/revoke')
        && $request['token'] === 'a-live-token');
});

it('deletes the twitch event rows rather than detaching them', function () {
    $user = User::factory()->create();

    TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_name' => 'SomeFollower'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    app(UserDeletionService::class)->eraseAccount($user);

    expect(TwitchEvent::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
    // And not merely orphaned with a null user_id.
    expect(TwitchEvent::withoutGlobalScopes()
        ->whereJsonContains('event_data->user_name', 'SomeFollower')
        ->count())->toBe(0);
});

it('deletes the session rows, which no foreign key reaches', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'test-session-'.$user->id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    app(UserDeletionService::class)->eraseAccount($user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('removes the image rows so nothing is left pointing at the bucket', function () {
    $user = User::factory()->create();

    ImageUpload::create([
        'user_id' => $user->id,
        'path' => 'overlays/screenshots/abc.webp',
        'url' => 'https://images.overlabels.com/overlays/screenshots/abc.webp',
        'kind' => 'template_screenshot',
        'bytes' => 100,
        'width' => 1280,
        'height' => 720,
        'format' => 'webp',
    ]);

    app(UserDeletionService::class)->eraseAccount($user);

    expect(ImageUpload::where('user_id', $user->id)->count())->toBe(0);
});

it('takes the user name out of admin audit rows when the user asked to be erased', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['name' => 'DeletedPerson', 'twitch_id' => '987654']);

    AdminAuditLog::create([
        'admin_id' => $admin->id,
        'action' => 'user.role_changed',
        'target_type' => 'User',
        'target_id' => $user->id,
        'metadata' => ['user_name' => 'DeletedPerson', 'twitch_id' => '987654', 'from' => 'user', 'to' => 'admin'],
        'ip_address' => '203.0.113.1',
    ]);

    app(UserDeletionService::class)->eraseAccount($user, redactAuditTrail: true);

    $row = AdminAuditLog::where('target_id', $user->id)->sole();

    // The action survives; the person does not.
    expect($row->action)->toBe('user.role_changed')
        ->and($row->metadata['from'])->toBe('user')
        ->and($row->metadata['user_name'])->toBe('[erased]')
        ->and($row->metadata['twitch_id'])->toBe('[erased]');
});

it('keeps the audit trail intact for an admin-initiated deletion', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['name' => 'BannedPerson', 'twitch_id' => '111222']);

    AdminAuditLog::create([
        'admin_id' => $admin->id,
        'action' => 'user.deleted',
        'target_type' => 'User',
        'target_id' => $user->id,
        'metadata' => ['user_name' => 'BannedPerson', 'twitch_id' => '111222', 'strategy' => 'delete_all'],
        'ip_address' => '203.0.113.1',
    ]);

    // No redactAuditTrail flag: this is the admin path, where the record of who
    // was removed and why is the point. It ages out on the two-year sweep.
    app(UserDeletionService::class)->eraseAccount($user);

    $row = AdminAuditLog::where('target_id', $user->id)->sole();

    expect($row->metadata['user_name'])->toBe('BannedPerson');
});

it('still finishes the erasure when an external cleanup step fails', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    $user = User::factory()->create(['access_token' => 'a-token']);

    app(UserDeletionService::class)->eraseAccount($user);

    // A third party being unreachable must not leave the user with an account
    // they asked us to destroy.
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
