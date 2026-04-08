<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\UserEventsubSubscription;
use App\Services\External\ExternalServiceRegistry;
use App\Services\UserEventSubManager;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
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

    private function serviceName(string $key): string
    {
        return match ($key) {
            'kofi' => 'Ko-fi',
            'gpslogger' => 'GPSLogger',
            'streamlabs' => 'StreamLabs',
            'streamelements' => 'StreamElements',
            'throne' => 'Throne',
            'patreon' => 'Patreon',
            'fourthwall' => 'Fourthwall',
            'buymeacoffee' => 'Buy Me a Coffee',
            default => ucfirst($key),
        };
    }
}
