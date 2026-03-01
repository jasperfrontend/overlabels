<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KofiIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'kofi')
            ->first();

        $webhookUrl = $integration
            ? url("/api/webhooks/kofi/{$integration->webhook_token}")
            : null;

        $credentials = $integration?->getCredentialsDecrypted() ?? [];

        return Inertia::render('settings/integrations/kofi', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'webhook_url' => $webhookUrl,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'settings' => $integration->settings ?? [],
                'has_token' => ! empty($credentials['verification_token']),
            ] : [
                'connected' => false,
                'enabled' => false,
                'webhook_url' => null,
                'last_received_at' => null,
                'settings' => [],
                'has_token' => false,
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'verification_token' => 'required|string|max:255',
            'enabled_events' => 'nullable|array',
            'enabled_events.*' => 'string|in:donation,subscription,shop_order,commission',
            'enabled' => 'nullable|boolean',
        ]);

        $isNew = ! ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'kofi'],
            ['enabled' => true]
        );

        // Encrypt and store credentials
        $integration->setCredentialsEncrypted([
            'verification_token' => $validated['verification_token'],
        ]);

        $integration->settings = [
            'enabled_events' => $validated['enabled_events'] ?? ['donation', 'subscription', 'shop_order'],
        ];

        $integration->enabled = $validated['enabled'] ?? true;
        $integration->save();

        // Auto-provision controls on first connection
        if ($isNew) {
            $driver = ExternalServiceRegistry::driver('kofi');
            $this->controlService->provision($user, $driver);
        }

        return back()->with('success', 'Ko-fi integration saved.');
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'kofi')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'kofi');
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'Ko-fi disconnected.');
    }
}
