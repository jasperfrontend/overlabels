<?php

namespace App\Http\Controllers;

use App\Models\OverlayReport;
use App\Models\OverlayTemplate;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * Accepts reports about publicly listed overlays.
 *
 * Open to logged-out visitors, because a public overlay is mostly seen by
 * people arriving from a shared link who have no account. That makes this the
 * only unauthenticated write endpoint on the site aimed at humans, so it
 * carries three cheap anti-spam layers instead of a captcha: a honeypot field,
 * a server-signed timing trap, and a tight per-IP rate limit (see the
 * `overlay-report` limiter in AppServiceProvider).
 */
class OverlayReportController extends Controller
{
    /**
     * Nobody reads an overlay, opens the dialog and writes a real reason in
     * under four seconds. Bots post instantly.
     */
    private const MIN_FILL_SECONDS = 4;

    /**
     * Mint the signed render timestamp that backs the timing trap. Signed so a
     * bot cannot simply back-date the field to look like a slow human.
     *
     * Deliberately never expires: its only job is to prove the timestamp came
     * from us. Expiring it would silently reject a real person who left the
     * tab open, and volume is already capped by the rate limiter.
     */
    public static function issueTicket(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $template = OverlayTemplate::where('slug', $slug)->firstOrFail();

        if (! $template->is_public) {
            abort(404, 'This overlay is private');
        }

        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:15', 'max:2000'],
            'email' => [Rule::requiredIf($user === null), 'nullable', 'email:rfc', 'max:255'],
            'ticket' => ['nullable', 'string'],
            'website' => ['nullable', 'string'],
        ], [
            'reason.min' => 'Please describe the problem in a bit more detail.',
            'email.required' => 'An email address is required so we can follow up on your report.',
        ]);

        // Automated submissions get the ordinary success response and nothing
        // is written. Telling a bot which check it tripped is how it learns to
        // pass the next one.
        if ($this->looksAutomated($request)) {
            return back();
        }

        // One open report per reporter per overlay. Stops a double-clicked
        // submit button and stops one person padding the queue.
        $existing = OverlayReport::query()
            ->where('overlay_template_id', $template->id)
            ->where('status', OverlayReport::STATUS_OPEN)
            ->when($user, fn ($q) => $q->where('reporter_user_id', $user->id))
            ->when(! $user, fn ($q) => $q->where('reporter_email', $validated['email']))
            ->exists();

        if ($existing) {
            return back();
        }

        OverlayReport::create([
            'overlay_template_id' => $template->id,
            'template_slug' => $template->slug,
            'template_name' => $template->name,
            'reporter_user_id' => $user?->id,
            'reporter_email' => $user ? null : $validated['email'],
            'reason' => $validated['reason'],
            'status' => OverlayReport::STATUS_OPEN,
            'ip_address' => $request->ip(),
        ]);

        return back();
    }

    private function looksAutomated(Request $request): bool
    {
        // Honeypot: a field hidden from humans that bots fill in anyway.
        if (filled($request->input('website'))) {
            return true;
        }

        try {
            $renderedAt = (int) Crypt::decryptString((string) $request->input('ticket'));
        } catch (DecryptException) {
            // Missing or forged ticket. A real browser always sends ours back.
            return true;
        }

        return now()->timestamp - $renderedAt < self::MIN_FILL_SECONDS;
    }
}
