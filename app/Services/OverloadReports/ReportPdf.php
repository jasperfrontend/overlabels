<?php

namespace App\Services\OverloadReports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prints an Overload Report page to PDF through Gotenberg (#345): the page's
 * own HTML, as-is, to Chromium's HTML route. Gotenberg is private, behind
 * basic auth (config services.gotenberg). Never its /health path: that one
 * has no auth.
 */
class ReportPdf
{
    public const int TIMEOUT_SECONDS = 20;

    public static function configured(): bool
    {
        return filled(config('services.gotenberg.url'));
    }

    /** The PDF bytes, or null when the printer is not configured or failed. */
    public function print(string $html): ?string
    {
        if (! self::configured()) {
            return null;
        }

        try {
            $response = Http::withBasicAuth((string) config('services.gotenberg.username'), (string) config('services.gotenberg.password'))
                ->timeout(self::TIMEOUT_SECONDS)
                ->attach('files', $html, 'index.html')
                ->post(rtrim((string) config('services.gotenberg.url'), '/').'/forms/chromium/convert/html');
        } catch (Throwable $e) {
            Log::warning("Overload Report PDF: printer unreachable: {$e->getMessage()}");

            return null;
        }

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF')) {
            Log::warning('Overload Report PDF: printer answered '.$response->status());

            return null;
        }

        return $response->body();
    }
}
