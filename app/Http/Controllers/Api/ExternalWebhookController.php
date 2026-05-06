<?php

namespace App\Http\Controllers\Api;

use App\Contracts\StatefulExternalServiceDriver;
use App\Http\Controllers\Controller;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Services\External\ExternalAlertService;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\LockdownService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ExternalWebhookController extends Controller
{
    /**
     * URL slug aliases. The webhook URL keeps the historical slug for the
     * Overlabels mobile app, while the canonical service key is `gps`.
     */
    private const URL_SLUG_ALIASES = [
        'overlabels-mobile' => 'gps',
    ];

    public function __construct(
        private readonly ExternalAlertService $alertService,
        private readonly ExternalControlService $controlService,
    ) {}

    /**
     * Translate the URL slug into the canonical service key used internally
     * (DB columns, registry lookups, driver matching).
     */
    private function canonicalService(string $urlSlug): string
    {
        return self::URL_SLUG_ALIASES[$urlSlug] ?? $urlSlug;
    }

    /**
     * GET /api/webhooks/{service}/{webhook_token}
     *
     * Landing page shown when a user scans the QR code on their phone.
     * Displays the webhook URL with a copy button so they can paste it
     * into GPSLogger's "Log to custom URL" settings.
     */
    public function show(string $service, string $webhookToken): View
    {
        $canonical = $this->canonicalService($service);

        if (! in_array($canonical, ['gpslogger', 'gps'])) {
            abort(404);
        }

        if (! ExternalServiceRegistry::has($canonical)) {
            abort(404);
        }

        $integration = ExternalIntegration::where('webhook_token', $webhookToken)
            ->where('service', $canonical)
            ->first();

        if (! $integration) {
            abort(404);
        }

        $webhookUrl = url("/api/webhooks/{$service}/{$webhookToken}");

        if ($canonical === 'gps') {
            $credentials = $integration->getCredentialsDecrypted();
            $token = $credentials['token'] ?? '';
            $deepLink = 'overlabels://gps-setup?'
                .http_build_query(['endpoint' => $webhookUrl, 'token' => $token]);

            return view('webhook-landing-mobile', [
                'webhookUrl' => $webhookUrl,
                'deepLink' => $deepLink,
            ]);
        }

        return view('webhook-landing', ['webhookUrl' => $webhookUrl]);
    }

    /**
     * POST /api/webhooks/{service}/{webhook_token}
     */
    public function handle(Request $request, string $service, string $webhookToken): JsonResponse
    {
        $service = $this->canonicalService($service);

        if (app(LockdownService::class)->isActive()) {
            return response()->json(['ok' => true]); // absorb silently during lockdown
        }

        // 1. Validate service key
        if (! ExternalServiceRegistry::has($service)) {
            return response()->json(['error' => 'Unknown service.'], 404);
        }

        // 2. Find integration by webhook token
        $integration = ExternalIntegration::where('webhook_token', $webhookToken)
            ->where('service', $service)
            ->where('enabled', true)
            ->with('user')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Integration not found.'], 404);
        }

        $user = $integration->user;

        // 3. Resolve driver
        $driver = ExternalServiceRegistry::driver($service);

        // 4. Verify request authenticity
        if (! $driver->verifyRequest($request, $integration)) {
            Log::warning("External webhook verification failed for user {$user->id}", [
                'service' => $service,
            ]);

            return response()->json(['error' => 'Verification failed.'], 403);
        }

        // 5. Parse raw payload
        $payload = $this->parsePayload($request, $service);

        // 6. Determine event type
        $eventType = $driver->parseEventType($payload);

        if (! $eventType) {
            return response()->json(['status' => 'ignored', 'reason' => 'Unsupported event type.']);
        }

        // 6b. Handle settings_sync (no event storage, no controls, no alerts)
        if ($eventType === 'settings_sync') {
            return $this->handleSettingsSync($integration, $payload);
        }

        // 7. Normalize event
        $normalizedEvent = $driver->normalizeEvent($payload, $eventType);

        // 8. Store in external_events (dedup check)

        // In test mode, append a unique suffix so the same payload can be fired
        // repeatedly without hitting the dedup constraint.
        $messageId = $integration->test_mode
            ? $normalizedEvent->getMessageId().'_test_'.microtime(true)
            : $normalizedEvent->getMessageId();

        $privateMetadata = $normalizedEvent->getPrivateMetadata() ?? [];
        if ($normalizedEvent->getSupporterEmail() !== null) {
            $privateMetadata['supporter_email'] = $normalizedEvent->getSupporterEmail();
        }

        try {
            $storedEvent = ExternalEvent::create([
                'user_id' => $user->id,
                'service' => $service,
                'event_type' => $eventType,
                'message_id' => $messageId,
                // Drivers may strip PII (e.g. BMAC removes supporter_email,
                // shipping_address, total_amount_charged) before exposing the
                // payload via getRaw(); fall back to the parsed body for
                // drivers that don't override.
                'raw_payload' => $normalizedEvent->getRaw() ?: $payload,
                'normalized_payload' => $normalizedEvent->getTemplateTags(),
                'supporter_email_hash' => $normalizedEvent->getSupporterEmailHash(),
                'private_metadata' => empty($privateMetadata) ? null : $privateMetadata,
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::info('Duplicate external event ignored', [
                'service' => $service,
                'message_id' => $normalizedEvent->getMessageId(),
            ]);

            return response()->json(['status' => 'duplicate']);
        }

        // 9. Update service-managed controls
        $controlUpdates = $driver->getControlUpdates($normalizedEvent);

        if ($driver instanceof StatefulExternalServiceDriver) {
            $driver->beforeControlUpdates($integration, $normalizedEvent, $controlUpdates);
        }

        if (! empty($controlUpdates)) {
            $this->controlService->applyUpdates($user, $service, $controlUpdates);
            $storedEvent->update(['controls_updated' => true]);
        }

        // 10. Dispatch alert
        $alertDispatched = $this->alertService->dispatch($normalizedEvent, $user);
        if ($alertDispatched) {
            $storedEvent->update(['alert_dispatched' => true]);
        }

        // 11. Update integration's last_received_at
        $integration->update(['last_received_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle a settings_sync event from the mobile app.
     * Persists safe zones (and future settings) into the integration's settings jsonb.
     * Does NOT create an external_events row.
     *
     * `safe_zones` is a JSON-encoded string in the form-encoded body:
     *   safe_zones=[{"id":"...","lat":51.92,"lng":4.47,"radius":500}, ...]
     * - Empty array `[]` clears all zones.
     * - Field absent = no change.
     * - Malformed JSON or wrong shape: log and no-op.
     */
    private function handleSettingsSync(ExternalIntegration $integration, array $payload): JsonResponse
    {
        $settings = $integration->settings ?? [];

        // Drop any legacy single-zone keys regardless of payload shape.
        unset($settings['safe_zone_lat'], $settings['safe_zone_lng'], $settings['safe_zone_radius']);

        if (array_key_exists('safe_zones', $payload)) {
            $rawJson = $payload['safe_zones'];
            $zones = is_string($rawJson) ? json_decode($rawJson, true) : null;

            if (! is_array($zones)) {
                Log::warning('settings_sync: malformed safe_zones payload', [
                    'integration_id' => $integration->id,
                ]);
            } else {
                $settings['safe_zones'] = $this->sanitizeSafeZones($zones, $integration->id);
            }
        }

        $integration->settings = $settings;
        $integration->save();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Validate and clamp incoming safe zones. Drops bad rows; keeps good ones.
     * Caps the result at 10 zones (extras are dropped with a log line).
     */
    private function sanitizeSafeZones(array $zones, int $integrationId): array
    {
        $out = [];

        foreach ($zones as $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $id = $zone['id'] ?? null;
            $lat = $zone['lat'] ?? null;
            $lng = $zone['lng'] ?? null;
            $radius = $zone['radius'] ?? null;

            if (! is_string($id) || $id === '' || strlen($id) > 64) {
                continue;
            }
            if (! is_numeric($lat) || $lat < -90 || $lat > 90) {
                continue;
            }
            if (! is_numeric($lng) || $lng < -180 || $lng > 180) {
                continue;
            }
            if (! is_numeric($radius)) {
                continue;
            }

            $radiusInt = max(1, min(50000, (int) round((float) $radius)));

            $out[] = [
                'id' => $id,
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'radius' => $radiusInt,
            ];
        }

        if (count($out) > 10) {
            Log::info('settings_sync: trimmed safe_zones to first 10', [
                'integration_id' => $integrationId,
                'received' => count($out),
            ]);
            $out = array_slice($out, 0, 10);
        }

        return $out;
    }

    /**
     * Parse the raw request payload based on service-specific encoding.
     */
    private function parsePayload(Request $request, string $service): array
    {
        // Ko-fi sends `application/x-www-form-urlencoded` with a `data` JSON field
        if ($service === 'kofi') {
            $data = $request->input('data');
            if (is_string($data)) {
                return json_decode($data, true) ?? [];
            }
        }

        // Try JSON body first
        $payload = $request->json()->all();
        if (! empty($payload)) {
            return $payload;
        }

        // Try form/query parameters (populated when Content-Type is set)
        $all = $request->all();
        if (! empty($all)) {
            return $all;
        }

        // Fallback: parse raw body as query string (e.g. GPSLogger
        // sends "lat=52.37&lon=4.90&..." without a Content-Type header,
        // so PHP does not populate $_POST automatically)
        $raw = $request->getContent();
        if (! empty($raw) && str_contains($raw, '=')) {
            parse_str($raw, $parsed);
            if (! empty($parsed)) {
                return $parsed;
            }
        }

        return [];
    }
}
