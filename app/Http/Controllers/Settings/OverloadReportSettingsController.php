<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\BotModeratedChannels;
use App\Services\OverloadReports\BusyStretch;
use App\Services\OverloadReports\ReportKinds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overlabels Overload Report settings (#345), at /settings/overload-report.
 * Everything lives in the user's preferences: on/off, threshold, quiet spell,
 * which kinds count, and a test mode that switches itself off after an hour.
 */
class OverloadReportSettingsController extends Controller
{
    public const int TEST_MODE_MINUTES = 60;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $settings = BusyStretch::settings($user);

        return Inertia::render('settings/OverloadReport', [
            'settings' => [
                'enabled' => $settings['enabled'],
                'threshold' => $settings['threshold'],
                'quiet_seconds' => $settings['quiet_seconds'],
                'events' => $settings['events'],
                'test_mode' => BusyStretch::testModeOn($user),
                'test_until' => BusyStretch::testModeOn($user) ? $settings['test_until'] : null,
            ],
            'kinds' => collect(ReportKinds::KINDS)->map(fn (array $meta, string $key) => ['key' => $key, 'label' => $meta['label']])->values(),
            'bot' => match (true) {
                ! $user->bot_enabled => 'off',
                default => match (app(BotModeratedChannels::class)->moderates((string) $user->twitch_id)) {
                    true => 'modded',
                    false => 'not_modded',
                    null => 'unknown',
                },
            },
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'threshold' => ['required', 'integer', 'min:5', 'max:100000'],
            'quiet_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'events' => ['required', 'array:'.implode(',', array_keys(ReportKinds::KINDS))],
            'events.*' => ['boolean'],
            'test_mode' => ['required', 'boolean'],
        ], [
            'threshold.min' => 'A report needs at least 5 events.',
            'quiet_seconds.min' => 'The quiet spell is at least 10 seconds.',
            'quiet_seconds.max' => 'The quiet spell is at most 10 minutes.',
        ]);

        $events = [];
        foreach (array_keys(ReportKinds::KINDS) as $kind) {
            $events[$kind] = (bool) ($validated['events'][$kind] ?? false);
        }

        // Switching test mode on starts its hour; leaving it on keeps the hour
        // it already has, so saving another field does not extend it.
        $testUntil = match (true) {
            ! $validated['test_mode'] => null,
            BusyStretch::testModeOn($user) => BusyStretch::settings($user)['test_until'],
            default => now()->addMinutes(self::TEST_MODE_MINUTES)->toIso8601String(),
        };

        $user->setPreference('overload_report', [
            'enabled' => (bool) $validated['enabled'],
            'threshold' => (int) $validated['threshold'],
            'quiet_seconds' => (int) $validated['quiet_seconds'],
            'events' => $events,
            'test_until' => $testUntil,
        ])->save();
        BusyStretch::settingsChanged($user);

        return back()->with('success', 'Overload Report settings saved.');
    }
}
