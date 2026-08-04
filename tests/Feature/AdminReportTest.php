<?php

use App\Models\AdminAuditLog;
use App\Models\OverlayReport;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function adminReportUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ], $attrs));
}

function reportAdmin(): User
{
    return adminReportUser(['role' => 'admin']);
}

function reportedTemplate(): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => adminReportUser()->id,
        'fork_of_id' => null,
        'type' => 'static',
        'is_public' => true,
        'slug' => 'ovl-'.fake()->unique()->lexify('????????'),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Access
// ──────────────────────────────────────────────────────────────────────────────

test('non-admins get a 404 on the reports page', function () {
    $this->actingAs(adminReportUser())
        ->get('/admin/reports')
        ->assertNotFound();
});

test('logged out visitors get a 404 on the reports page', function () {
    $this->get('/admin/reports')->assertNotFound();
});

test('an admin sees the reports page', function () {
    OverlayReport::factory()->about(reportedTemplate())->create(['reason' => 'Stolen artwork.']);

    $this->actingAs(reportAdmin())
        ->get('/admin/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/index')
            ->has('reports.data', 1)
            ->where('reports.data.0.reason', 'Stolen artwork.')
            ->where('stats.open', 1)
        );
});

test('the page defaults to open reports', function () {
    $template = reportedTemplate();
    OverlayReport::factory()->about($template)->create(['reason' => 'Still waiting.']);
    OverlayReport::factory()->about($template)->read()->create(['reason' => 'Already handled.']);

    $this->actingAs(reportAdmin())
        ->get('/admin/reports')
        ->assertInertia(fn ($page) => $page
            ->has('reports.data', 1)
            ->where('reports.data.0.reason', 'Still waiting.')
        );

    $this->actingAs(reportAdmin())
        ->get('/admin/reports?status=read')
        ->assertInertia(fn ($page) => $page
            ->has('reports.data', 1)
            ->where('reports.data.0.reason', 'Already handled.')
        );
});

test('an anonymous reporter is flagged as unverified', function () {
    $template = reportedTemplate();
    $reporter = adminReportUser();

    OverlayReport::factory()->about($template)->create([
        'reporter_user_id' => $reporter->id,
        'reporter_email' => null,
    ]);
    OverlayReport::factory()->about($template)->create(['reporter_email' => 'anon@example.com']);

    $this->actingAs(reportAdmin())
        ->get('/admin/reports')
        ->assertInertia(function ($page) use ($reporter) {
            $rows = collect($page->toArray()['props']['reports']['data'])->keyBy('id');
            $byLabel = $rows->keyBy(fn ($row) => $row['reporter']['label']);

            expect($byLabel[$reporter->name]['reporter']['is_authenticated'])->toBeTrue()
                ->and($byLabel['anon@example.com']['reporter']['is_authenticated'])->toBeFalse();
        });
});

// ──────────────────────────────────────────────────────────────────────────────
// Actions
// ──────────────────────────────────────────────────────────────────────────────

test('an admin can mark a report as read', function () {
    $report = OverlayReport::factory()->about(reportedTemplate())->create();
    $admin = reportAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.reports.update', $report), ['status' => 'read'])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe(OverlayReport::STATUS_READ)
        ->and($report->reviewed_by_id)->toBe($admin->id)
        ->and($report->reviewed_at)->not->toBeNull();
});

test('marking a report read is reversible', function () {
    $report = OverlayReport::factory()->about(reportedTemplate())->read()->create();

    $this->actingAs(reportAdmin())
        ->patch(route('admin.reports.update', $report), ['status' => 'open'])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe(OverlayReport::STATUS_OPEN)
        ->and($report->reviewed_at)->toBeNull()
        ->and($report->reviewed_by_id)->toBeNull();
});

test('an admin can delete a report', function () {
    $report = OverlayReport::factory()->about(reportedTemplate())->create();

    $this->actingAs(reportAdmin())
        ->delete(route('admin.reports.destroy', $report))
        ->assertRedirect();

    expect(OverlayReport::find($report->id))->toBeNull();
});

test('deleting a report copies its reason into the audit log first', function () {
    $report = OverlayReport::factory()->about(reportedTemplate())->create([
        'reason' => 'Contains a phishing link in the footer.',
    ]);

    $this->actingAs(reportAdmin())->delete(route('admin.reports.destroy', $report));

    $entry = AdminAuditLog::where('action', 'report.deleted')->sole();

    expect($entry->metadata['reason'])->toBe('Contains a phishing link in the footer.')
        ->and($entry->target_id)->toBe($report->id);
});

test('non-admins cannot act on reports', function () {
    $report = OverlayReport::factory()->about(reportedTemplate())->create();
    $user = adminReportUser();

    $this->actingAs($user)
        ->patch(route('admin.reports.update', $report), ['status' => 'read'])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('admin.reports.destroy', $report))
        ->assertNotFound();

    expect($report->fresh()->status)->toBe(OverlayReport::STATUS_OPEN);
});
