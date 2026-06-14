<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function scopeUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function scopeTemplate(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null]);
}

function makeServiceControl(User $user, ?int $templateId, string $value = '0', string $source = 'kofi', string $key = 'donations_received'): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => $templateId,
        'source' => $source,
        'key' => $key,
        'type' => 'counter',
        'value' => $value,
        'source_managed' => true,
        'sort_order' => 0,
    ]);
}

function consolidationMigration(): object
{
    return include base_path('database/migrations/2026_06_14_120000_consolidate_service_controls.php');
}

// ──────────────────────────────────────────────────────────────────────────────
// Guard: service presets are user-scoped, never per-overlay
// ──────────────────────────────────────────────────────────────────────────────

test('adding a service preset to an overlay creates a user-scoped control, not a per-overlay copy', function () {
    $user = scopeUser();
    $template = scopeTemplate($user);

    $this->actingAs($user)
        ->postJson("/templates/{$template->id}/controls", [
            'key' => 'donations_received',
            'type' => 'counter',
            'source' => 'kofi',
        ])
        ->assertStatus(201);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'kofi',
        'key' => 'donations_received',
        'overlay_template_id' => null,
        'source_managed' => true,
    ]);

    $this->assertDatabaseMissing('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'kofi',
        'key' => 'donations_received',
        'overlay_template_id' => $template->id,
    ]);
});

test('re-adding a service preset is idempotent (one user-scoped row)', function () {
    $user = scopeUser();
    $template = scopeTemplate($user);

    foreach (range(1, 2) as $_) {
        $this->actingAs($user)
            ->postJson("/templates/{$template->id}/controls", [
                'key' => 'donations_received', 'type' => 'counter', 'source' => 'kofi',
            ])->assertStatus(201);
    }

    expect(OverlayControl::where('user_id', $user->id)->where('source', 'kofi')->where('key', 'donations_received')->count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Consolidation migration
// ──────────────────────────────────────────────────────────────────────────────

test('migration collapses duplicates into the existing user-scoped row', function () {
    $user = scopeUser();
    $t1 = scopeTemplate($user);
    $t2 = scopeTemplate($user);

    makeServiceControl($user, null, '5');      // user-scoped (wins)
    makeServiceControl($user, $t1->id, '5');   // duplicate
    makeServiceControl($user, $t2->id, '5');   // duplicate

    consolidationMigration()->up();

    $rows = OverlayControl::where('user_id', $user->id)->where('source', 'kofi')->where('key', 'donations_received')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->overlay_template_id)->toBeNull()
        ->and($rows->first()->value)->toBe('5');
});

test('migration promotes the freshest template-scoped row when no user-scoped row exists', function () {
    $user = scopeUser();
    $t1 = scopeTemplate($user);
    $t2 = scopeTemplate($user);

    $older = makeServiceControl($user, $t1->id, 'OLD');
    $newer = makeServiceControl($user, $t2->id, 'NEW');
    DB::table('overlay_controls')->where('id', $newer->id)->update(['updated_at' => now()->addMinute()]);

    consolidationMigration()->up();

    $rows = OverlayControl::where('user_id', $user->id)->where('source', 'kofi')->where('key', 'donations_received')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->overlay_template_id)->toBeNull()
        ->and($rows->first()->value)->toBe('NEW');
});

test('migration is idempotent', function () {
    $user = scopeUser();
    $t1 = scopeTemplate($user);

    makeServiceControl($user, null, '3');
    makeServiceControl($user, $t1->id, '3');

    consolidationMigration()->up();
    consolidationMigration()->up();

    expect(OverlayControl::where('user_id', $user->id)->where('source', 'kofi')->where('key', 'donations_received')->count())->toBe(1);
});
