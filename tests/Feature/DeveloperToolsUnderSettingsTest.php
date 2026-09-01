<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The four developer-tool pages moved from the root to /settings/* on
 * 2026-09-02. Route NAMES did not change - help `context:` lines and the
 * command palette key on names - and every old path 301s to the new one so
 * bookmarks and help links in the wild keep working.
 */
const MOVED_DEVELOPER_TOOLS = [
    'tokens.index' => ['/tokens', '/settings/tokens'],
    'tags.generator' => ['/tags', '/settings/tags'],
    'twitchdata' => ['/twitchdata', '/settings/twitchdata'],
    'testing.index' => ['/testing', '/settings/testing'],
];

test('each developer tool route name resolves under /settings', function () {
    foreach (MOVED_DEVELOPER_TOOLS as $name => [, $newPath]) {
        expect(route($name, absolute: false))->toBe($newPath, "route('{$name}')");
    }
});

test('each old root path permanently redirects to its /settings path', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $this->actingAs($user);

    foreach (MOVED_DEVELOPER_TOOLS as [$oldPath, $newPath]) {
        $this->get($oldPath)
            ->assertStatus(301)
            ->assertRedirect($newPath);
    }
});
