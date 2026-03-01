<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Services\External\ExternalAlertService;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExternalWebhookController extends Controller
{
    public function __construct(
        private readonly ExternalAlertService $alertService,
        private readonly ExternalControlService $controlService,
    ) {}

    /**
     * POST /api/webhooks/{service}/{webhook_token}
     */
    public function handle(Request $request, string $service, string $webhookToken): JsonResponse
    {
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
            return response()->json(['status' => 'ignored', 'reason' => 'Unsupported event type.'], 200);
        }

        // 7. Normalize event
        $normalizedEvent = $driver->normalizeEvent($payload, $eventType);

        // 8. Store in external_events (dedup check)

        // In test mode, append a unique suffix so the same payload can be fired
        // repeatedly without hitting the dedup constraint.
        $messageId = $integration->test_mode
            ? $normalizedEvent->getMessageId() . '_test_' . microtime(true)
            : $normalizedEvent->getMessageId();

        try {
            $storedEvent = ExternalEvent::create([
                'user_id' => $user->id,
                'service' => $service,
                'event_type' => $eventType,
                'message_id' => $messageId,
                'raw_payload' => $payload,
                'normalized_payload' => $normalizedEvent->getTemplateTags(),
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::info("Duplicate external event ignored", [
                'service' => $service,
                'message_id' => $normalizedEvent->getMessageId(),
            ]);

            return response()->json(['status' => 'duplicate'], 409);
        }

        // 9. Update service-managed controls
        $controlUpdates = $driver->getControlUpdates($normalizedEvent);
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

        return response()->json(['status' => 'ok'], 200);
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

        // Default: JSON body
        return $request->json()->all() ?? [];
    }
}
