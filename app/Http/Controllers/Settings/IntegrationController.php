<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Services\External\ExternalServiceRegistry;
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
                'last_received_at' => $integration?->last_received_at?->toIso8601String(),
            ];
        }, ExternalServiceRegistry::services());

        return Inertia::render('settings/integrations/index', [
            'services' => array_values($services),
        ]);
    }

    private function serviceName(string $key): string
    {
        return match ($key) {
            'kofi' => 'Ko-fi',
            'throne' => 'Throne',
            'patreon' => 'Patreon',
            'fourthwall' => 'Fourthwall',
            'buymeacoffee' => 'Buy Me a Coffee',
            default => ucfirst($key),
        };
    }
}
