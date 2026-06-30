<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Services\External\ExternalControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThroneIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'throne')
            ->first();

        $webhookUrl = $integration
            ? url("/api/webhooks/throne/$integration->webhook_token")
            : null;

        $settings = $integration->settings ?? [];

        return Inertia::render('settings/integrations/throne', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'test_mode' => $integration->test_mode,
                'webhook_url' => $webhookUrl,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'donations_seed_set' => ! empty($settings['donations_seed_set']),
                'donations_seed_value' => $settings['donations_seed_value'] ?? null,
            ] : [
                'connected' => false,
                'enabled' => false,
                'test_mode' => false,
                'webhook_url' => null,
                'last_received_at' => null,
                'donations_seed_set' => false,
                'donations_seed_value' => null,
            ],
        ]);
    }

    /**
     * Throne needs no user-supplied credentials: it signs every webhook with its
     * own global Ed25519 key (pinned in config), so connecting just provisions the
     * integration and surfaces the webhook URL the user pastes into Throne. The
     * routing token (webhook_token) is generated on create by the model.
     */
    public function connect(): RedirectResponse
    {
        $user = auth()->user();

        ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'throne'],
            ['enabled' => true],
        );

        return back()->with('success', 'Throne connected. Copy your webhook URL into Throne.');
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'throne')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate(['test_mode' => 'required|boolean']);

        $integration->update(['test_mode' => $validated['test_mode']]);

        // When test mode is turned OFF, reset all service-managed controls to defaults
        // (re-applying the seeded starting count if one was set).
        if (! $validated['test_mode']) {
            $settings = $integration->settings ?? [];
            $this->controlService->resetServiceManagedControls(
                $user,
                'throne',
                isset($settings['donations_seed_value']) ? (string) $settings['donations_seed_value'] : null,
            );
        }

        return response()->json(['test_mode' => $integration->test_mode]);
    }

    public function seedDonationCount(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'throne')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate([
            'initial_count' => 'required|integer|min:0|max:9999999',
        ]);

        $this->controlService->seedTotalReceived($user, 'throne', $validated['initial_count']);

        $settings = $integration->settings ?? [];
        $integration->settings = array_merge($settings, [
            'donations_seed_set' => true,
            'donations_seed_value' => $validated['initial_count'],
        ]);
        $integration->save();

        return response()->json([
            'donations_seed_set' => true,
            'donations_seed_value' => $validated['initial_count'],
        ]);
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'throne')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'throne');
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'Throne disconnected.');
    }
}
