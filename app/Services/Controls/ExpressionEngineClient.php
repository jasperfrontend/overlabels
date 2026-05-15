<?php

namespace App\Services\Controls;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the local expression-engine sidecar
 * (`expression-engine.mjs` at the repo root, deployed alongside the app
 * via Kamal). The sidecar shares its evaluator code with the frontend
 * overlay - one source of truth for Expression Control semantics.
 *
 * Failure modes are deliberately quiet. If the sidecar is unreachable or
 * returns an error, we log and return null. Callers (the recompute
 * listener, list-writer listener) treat null as "skip this update" rather
 * than crashing - the overlay still works because it has its own local
 * evaluator; the server-side persisted value just doesn't refresh until
 * the next dep update succeeds.
 */
class ExpressionEngineClient
{
    /**
     * Evaluate an expression server-side using the sidecar.
     *
     * @param  array<string,mixed>  $data  Flat key->value map matching the
     *                                     frontend's data shape: keys like
     *                                     "c:foo", "c:kofi:bar", "t:followers_total".
     * @return string|null The stringified result, or null on any failure
     *                     (parse error, sidecar down, timeout, bad response).
     */
    public function evaluate(string $expression, array $data): ?string
    {
        $url = rtrim(config('services.expression_engine.url'), '/');
        $secret = config('services.expression_engine.secret');
        $timeoutSeconds = max(1, (int) ceil(config('services.expression_engine.timeout_ms', 2000) / 1000));

        if (empty($url) || empty($secret)) {
            Log::warning('[expression-engine] not configured; skipping evaluation', [
                'expression' => $expression,
            ]);

            return null;
        }

        try {
            $response = Http::timeout($timeoutSeconds)
                ->withHeaders([
                    'X-Internal-Secret' => $secret,
                    'Content-Type' => 'application/json',
                ])
                ->post("$url/evaluate", [
                    'expression' => $expression,
                    'data' => $data,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('[expression-engine] sidecar unreachable', [
                'expression' => $expression,
                'err' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('[expression-engine] non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
                'expression' => $expression,
            ]);

            return null;
        }

        $body = $response->json();
        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            Log::debug('[expression-engine] eval returned not-ok', [
                'body' => $body,
                'expression' => $expression,
            ]);

            return null;
        }

        $value = $body['value'] ?? null;

        return $value === null ? null : (string) $value;
    }
}
