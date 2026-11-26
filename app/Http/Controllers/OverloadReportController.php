<?php

namespace App\Http\Controllers;

use App\Models\OverloadReport;
use App\Models\User;
use App\Services\MapSlugService;
use App\Services\OverloadReports\ReportKinds;
use App\Services\OverloadReports\ReportPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public Overlabels Overload Report page (#345):
 * /report/<the streamer's map slug>/<8 random digits>. No login, noindex,
 * rate-limited. Numbers and timestamps only.
 *
 * A Markdown twin at the same path plus `.md` is the export the streamer can
 * keep; the page's print stylesheet makes the browser's Save as PDF clean.
 */
class OverloadReportController extends Controller
{
    public function show(string $slug, string $code): Response
    {
        [$user, $report] = $this->resolve($slug, $code);

        return response($this->page($user, $report, $slug, null))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * The same page, printed by Gotenberg. The printer has no idea where the
     * reader is, so the page sends the reader's timezone (`tz`) and the times
     * are written in it here; anything that is not a real zone means UTC.
     */
    public function pdf(Request $request, string $slug, string $code): Response
    {
        [$user, $report] = $this->resolve($slug, $code);

        $tz = (string) $request->query('tz', 'UTC');
        if (! in_array($tz, timezone_identifiers_list(), true)) {
            $tz = 'UTC';
        }

        $pdf = app(ReportPdf::class)->print($this->page($user, $report, $slug, $tz));
        if ($pdf === null) {
            return response('The PDF printer is not available right now. Use your browser\'s Print instead, and choose Save as PDF.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="overload-report-'.$report->started_at->utc()->format('Y-m-d-Hi').'.pdf"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** The page's HTML. $tz set = the print copy: times fixed in that zone, no buttons, no script. */
    private function page(User $user, OverloadReport $report, string $slug, ?string $tz): string
    {
        $params = ['slug' => $slug, 'code' => $report->code];

        return view('overload-report.show', [
            'report' => $report,
            'streamerName' => $user->name,
            'dots' => $this->dots($report),
            'tz' => $tz,
            'markdownUrl' => route('overload-report.markdown', $params),
            'pdfUrl' => ReportPdf::configured() ? route('overload-report.pdf', $params) : null,
        ])->render();
    }

    public function markdown(string $slug, string $code): Response
    {
        [$user, $report] = $this->resolve($slug, $code);

        $lines = [
            '# Overlabels Overload Report',
            '',
            "{$user->name}'s stream, ".$report->started_at->utc()->format('F jS, Y').', '
                .$report->started_at->utc()->format('H:i').' to '.$report->ended_at->utc()->format('H:i').' UTC.',
            '',
            self::intro($user->name),
            '',
        ];
        foreach ($this->dots($report) as $dot) {
            $time = $dot['first']->utc()->format('H:i');
            $until = $dot['last']->utc()->format('H:i');
            $lines[] = "- **{$time}".($until !== $time ? " - {$until}" : '')." UTC** {$dot['title']} {$dot['line']}";
        }
        $lines[] = '';
        $lines[] = 'Kept on overlabels.com until '.$report->expiresAt()->utc()->format('F jS, Y').'.';

        $filename = 'overload-report-'.$report->started_at->utc()->format('Y-m-d-Hi').'.md';

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** The intro line. A feature, never an apology (#345). */
    public static function intro(string $name): string
    {
        return "Well done {$name}, your stream got busy! With this report you can quickly catch up on what happened.";
    }

    /**
     * @return array{0: User, 1: OverloadReport}
     */
    private function resolve(string $slug, string $code): array
    {
        $twitchId = app(MapSlugService::class)->decode($slug);
        $user = $twitchId === null ? null : User::where('twitch_id', $twitchId)->first();
        abort_if($user === null, 404);

        $report = OverloadReport::kept()->where('user_id', $user->id)->where('code', $code)->first();
        abort_if($report === null, 404);
        $report->setRelation('user', $user);

        return [$user, $report];
    }

    /**
     * @return list<array{first: CarbonImmutable, last: CarbonImmutable, title: string, line: string}>
     */
    private function dots(OverloadReport $report): array
    {
        return array_map(fn (array $dot) => [
            'first' => CarbonImmutable::parse($dot['first']),
            'last' => CarbonImmutable::parse($dot['last']),
            'title' => ReportKinds::title($dot),
            'line' => ReportKinds::line($dot),
        ], $report->timeline);
    }
}
