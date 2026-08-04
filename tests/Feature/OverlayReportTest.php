<?php

use App\Http\Controllers\OverlayReportController;
use App\Models\OverlayReport;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function reportUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ], $attrs));
}

function publicTemplate(array $attrs = []): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => reportUser()->id,
        'fork_of_id' => null,
        'type' => 'static',
        'is_public' => true,
        'slug' => 'ovl-'.fake()->unique()->lexify('????????'),
    ], $attrs));
}

/**
 * A ticket that already looks old enough to have been filled in by a human.
 */
function humanTicket(int $secondsAgo = 30): string
{
    return Crypt::encryptString((string) now()->subSeconds($secondsAgo)->timestamp);
}

function reportPayload(array $overrides = []): array
{
    return array_merge([
        'reason' => 'This overlay copies my branding wholesale and I never agreed to it.',
        'email' => 'reporter@example.com',
        'ticket' => humanTicket(),
        'website' => '',
    ], $overrides);
}

beforeEach(function () {
    RateLimiter::clear('overlay-report');
});

// ──────────────────────────────────────────────────────────────────────────────
// Submitting a report
// ──────────────────────────────────────────────────────────────────────────────

test('a logged out visitor can report a public overlay', function () {
    $template = publicTemplate();

    $this->post(route('overlay.report', $template->slug), reportPayload())
        ->assertRedirect();

    $report = OverlayReport::where('overlay_template_id', $template->id)->sole();

    expect($report->reporter_email)->toBe('reporter@example.com')
        ->and($report->reporter_user_id)->toBeNull()
        ->and($report->status)->toBe(OverlayReport::STATUS_OPEN)
        // Snapshot so the report stays readable after the overlay is deleted.
        ->and($report->template_slug)->toBe($template->slug)
        ->and($report->template_name)->toBe($template->name);
});

test('a logged in reporter is recorded by account, not by email', function () {
    $template = publicTemplate();
    $reporter = reportUser();

    $this->actingAs($reporter)
        ->post(route('overlay.report', $template->slug), reportPayload(['email' => null]))
        ->assertRedirect();

    $report = OverlayReport::where('overlay_template_id', $template->id)->sole();

    expect($report->reporter_user_id)->toBe($reporter->id)
        ->and($report->reporter_email)->toBeNull()
        ->and($report->reporterLabel())->toBe($reporter->name);
});

test('a logged out visitor must supply an email', function () {
    $template = publicTemplate();

    $this->post(route('overlay.report', $template->slug), reportPayload(['email' => null]))
        ->assertSessionHasErrors('email');

    expect(OverlayReport::count())->toBe(0);
});

test('a logged in reporter does not have to supply an email', function () {
    $template = publicTemplate();

    $this->actingAs(reportUser())
        ->post(route('overlay.report', $template->slug), reportPayload(['email' => null]))
        ->assertSessionHasNoErrors();

    expect(OverlayReport::count())->toBe(1);
});

test('a one word reason is rejected', function () {
    $template = publicTemplate();

    $this->post(route('overlay.report', $template->slug), reportPayload(['reason' => 'bad']))
        ->assertSessionHasErrors('reason');

    expect(OverlayReport::count())->toBe(0);
});

test('a private overlay cannot be reported', function () {
    $template = publicTemplate(['is_public' => false]);

    $this->post(route('overlay.report', $template->slug), reportPayload())
        ->assertNotFound();

    expect(OverlayReport::count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Anti-spam. Every rejection below returns the ordinary success response on
// purpose: telling a bot which check it tripped is how it learns to pass.
// ──────────────────────────────────────────────────────────────────────────────

test('a filled honeypot is silently discarded', function () {
    $template = publicTemplate();

    $this->post(route('overlay.report', $template->slug), reportPayload([
        'website' => 'https://spam.example',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(OverlayReport::count())->toBe(0);
});

test('an instant submission is silently discarded', function () {
    $template = publicTemplate();

    // Ticket minted right now: nobody reads an overlay and writes a reason in
    // under the minimum fill time.
    $this->post(route('overlay.report', $template->slug), reportPayload([
        'ticket' => OverlayReportController::issueTicket(),
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(OverlayReport::count())->toBe(0);
});

test('a forged or missing ticket is silently discarded', function () {
    $template = publicTemplate();

    // A bot that back-dates the timestamp cannot sign it.
    $this->post(route('overlay.report', $template->slug), reportPayload([
        'ticket' => (string) now()->subHour()->timestamp,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->post(route('overlay.report', $template->slug), reportPayload(['ticket' => null]))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(OverlayReport::count())->toBe(0);
});

test('the same reporter cannot stack open reports on one overlay', function () {
    $template = publicTemplate();
    $reporter = reportUser();

    $this->actingAs($reporter)->post(route('overlay.report', $template->slug), reportPayload());
    $this->actingAs($reporter)->post(route('overlay.report', $template->slug), reportPayload([
        'reason' => 'A second attempt at saying the same thing again.',
    ]));

    expect(OverlayReport::where('overlay_template_id', $template->id)->count())->toBe(1);
});

test('a different reporter can still report the same overlay', function () {
    $template = publicTemplate();

    $this->actingAs(reportUser())->post(route('overlay.report', $template->slug), reportPayload());
    $this->actingAs(reportUser())->post(route('overlay.report', $template->slug), reportPayload());

    expect(OverlayReport::where('overlay_template_id', $template->id)->count())->toBe(2);
});

test('the throttle cuts a flood of reports off', function () {
    $templates = collect(range(1, 5))->map(fn () => publicTemplate());

    // The limiter allows 3/hour per IP; the fourth is the one that must fail.
    foreach ($templates->take(3) as $template) {
        $this->post(route('overlay.report', $template->slug), reportPayload())
            ->assertRedirect();
    }

    $this->post(route('overlay.report', $templates[3]->slug), reportPayload())
        ->assertStatus(429);

    expect(OverlayReport::count())->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// A report outlives the overlay it is about
// ──────────────────────────────────────────────────────────────────────────────

test('deleting the overlay keeps the report and its snapshot', function () {
    $template = publicTemplate();

    $this->post(route('overlay.report', $template->slug), reportPayload());

    $slug = $template->slug;
    $name = $template->name;
    $template->delete();

    $report = OverlayReport::sole();

    expect($report->overlay_template_id)->toBeNull()
        ->and($report->template_slug)->toBe($slug)
        ->and($report->template_name)->toBe($name);
});

// ──────────────────────────────────────────────────────────────────────────────
// The public preview page
// ──────────────────────────────────────────────────────────────────────────────

test('the public preview ships a usable report ticket', function () {
    $template = publicTemplate();

    $response = $this->get(route('overlay.public', $template->slug));

    $ticket = $response->viewData('page')['props']['reportTicket'];

    expect((int) Crypt::decryptString($ticket))
        ->toBeGreaterThanOrEqual(now()->subMinute()->timestamp);
});
