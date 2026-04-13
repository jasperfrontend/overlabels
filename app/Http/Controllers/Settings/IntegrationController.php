<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\UserEventsubSubscription;
use App\Services\External\ExternalServiceRegistry;
use App\Services\UserEventSubManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(private readonly UserEventSubManager $eventSubManager) {}

    public function index(): Response
    {
        $user = auth()->user();

        $integrations = ExternalIntegration::where('user_id', $user->id)
            ->get()
            ->keyBy('service');

        $services = array_map(function (string $service) use ($integrations) {
            $integration = $integrations->get($service);

            return [
                'key' => $service,
                'name' => $this->serviceName($service),
                'connected' => (bool) $integration,
                'enabled' => $integration?->enabled ?? false,
                'test_mode' => $integration?->test_mode ?? false,
                'last_received_at' => $integration?->last_received_at?->toIso8601String(),
            ];
        }, ExternalServiceRegistry::services());

        $subscriptions = UserEventsubSubscription::where('user_id', $user->id)->get();
        $activeCount = $subscriptions->where('status', 'enabled')->count();

        $eventLabels = UserEventSubManager::getSupportedEventLabels();

        return Inertia::render('settings/integrations/index', [
            'services' => array_values($services),
            'eventsub' => [
                'connected' => $user->eventsub_connected_at !== null,
                'connected_at' => $user->eventsub_connected_at?->toIso8601String(),
                'subscription_count' => $subscriptions->count(),
                'active_count' => $activeCount,
                'supported_events' => array_map(
                    fn (string $label, string $key) => [
                        'key' => $key,
                        'label' => $label,
                        'active' => $subscriptions->where('event_type', $key)->where('status', 'enabled')->isNotEmpty(),
                    ],
                    $eventLabels,
                    array_keys($eventLabels),
                ),
            ],
        ]);
    }

    /**
     * Connect EventSub: wipe existing subscriptions and create a fresh set so the
     * user gets immediate feedback on which event types subscribed successfully.
     * Called from settings/integrations/index.vue via POST /eventsub/connect.
     */
    public function connectEventSub(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $this->eventSubManager->removeUserSubscriptions($user);
            $results = $this->eventSubManager->setupUserSubscriptions($user);

            $created = count($results['created']);
            $failed = count($results['failed']);
            $existing = count($results['existing']);

            Log::info('EventSub setup completed', [
                'user_id' => $user->id,
                'created' => $created,
                'failed' => $failed,
                'existing' => $existing,
            ]);

            return response()->json([
                'success' => true,
                'message' => "EventSub connected: $created created, $existing existing, $failed failed.",
            ]);
        } catch (Exception $e) {
            Log::error('Failed to setup EventSub', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to setup EventSub connections.',
            ], 500);
        }
    }

    private function serviceName(string $key): string
    {
        return match ($key) {
            'kofi' => 'Ko-fi',
            'gpslogger' => 'GPSLogger',
            'streamlabs' => 'Streamlabs',
            'streamelements' => 'StreamElements',
            'throne' => 'Throne',
            'patreon' => 'Patreon',
            'fourthwall' => 'Fourthwall',
            'buymeacoffee' => 'Buy Me a Coffee',
            default => ucfirst($key),
        };
    }
}
