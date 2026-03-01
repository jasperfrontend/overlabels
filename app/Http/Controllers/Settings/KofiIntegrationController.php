<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Services\External\ExternalControlService;
use Illuminate\Http\JsonResponse;
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

        $settings = $integration->settings ?? [];

        return Inertia::render('settings/integrations/kofi', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'test_mode' => $integration->test_mode,
                'webhook_url' => $webhookUrl,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'settings' => $settings,
                'has_token' => ! empty($credentials['verification_token']),
                'kofis_seed_set' => ! empty($settings['kofis_seed_set']),
                'kofis_seed_value' => $settings['kofis_seed_value'] ?? null,
            ] : [
                'connected' => false,
                'enabled' => false,
                'test_mode' => false,
                'webhook_url' => null,
                'last_received_at' => null,
                'settings' => [],
                'has_token' => false,
                'kofis_seed_set' => false,
                'kofis_seed_value' => null,
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

        // Merge so that one-time flags (e.g. kofis_seed_set) survive a re-save
        $integration->settings = array_merge(
            $integration->settings ?? [],
            ['enabled_events' => $validated['enabled_events'] ?? ['donation', 'subscription', 'shop_order']],
        );

        // Force enabled on first connection; respect the submitted value on updates.
        $integration->enabled = $isNew ? true : (bool) ($validated['enabled'] ?? true);
        $integration->save();

        return back()->with('success', 'Ko-fi integration saved.');
    }

    public function setTestMode(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'kofi')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate(['test_mode' => 'required|boolean']);

        $integration->update(['test_mode' => $validated['test_mode']]);

        return response()->json(['test_mode' => $integration->test_mode]);
    }

    public function seedDonationCount(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'kofi')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $settings = $integration->settings ?? [];

        if (! empty($settings['kofis_seed_set'])) {
            return response()->json(['error' => 'Starting count has already been set.'], 403);
        }

        $validated = $request->validate([
            'initial_count' => 'required|integer|min:0|max:9999999',
        ]);

        // Apply to all kofis_received controls belonging to this user
        OverlayControl::where('user_id', $user->id)
            ->where('source', 'kofi')
            ->where('key', 'kofis_received')
            ->where('source_managed', true)
            ->update(['value' => (string) $validated['initial_count']]);

        $integration->settings = array_merge($settings, [
            'kofis_seed_set' => true,
            'kofis_seed_value' => $validated['initial_count'],
        ]);
        $integration->save();

        return response()->json([
            'kofis_seed_set' => true,
            'kofis_seed_value' => $validated['initial_count'],
        ]);
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
