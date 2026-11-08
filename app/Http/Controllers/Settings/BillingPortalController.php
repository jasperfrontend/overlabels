<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaddleCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the signed-in user to their Paddle customer portal, where Paddle shows
 * their purchases and invoices. The Paddle customer is resolved from the
 * session's user server-side; a customer id from the client is never read.
 */
class BillingPortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $customer = PaddleCustomer::query()->where('user_id', $request->user()->id)->first();

        abort_if($customer === null, 404);

        $apiKey = (string) config('services.paddle.api_key');

        abort_if($apiKey === '', 503);

        $base = config('services.paddle.environment') === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(10)
            ->withBody('{}', 'application/json')
            ->post("{$base}/customers/{$customer->customer_id}/portal-sessions");

        $url = $response->json('data.urls.general.overview');

        if (! $response->successful() || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            Log::warning('Paddle portal session could not be created', ['status' => $response->status()]);

            abort(502);
        }

        return Inertia::location($url);
    }
}
