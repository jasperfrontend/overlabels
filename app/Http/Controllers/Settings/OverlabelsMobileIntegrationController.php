<?php

namespace App\Http\Controllers\Settings;

use App\Events\ControlValueUpdated;
use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OverlabelsMobileIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->first();

        $webhookUrl = $integration
            ? url("/api/webhooks/overlabels-mobile/{$integration->webhook_token}")
            : null;

        $token = null;
        if ($integration) {
            $credentials = $integration->getCredentialsDecrypted();
            $token = $credentials['token'] ?? null;
        }

        // Build the deep link for the QR code
        $deepLink = null;
        if ($webhookUrl && $token) {
            $deepLink = 'overlabels://gps-setup?'
                .http_build_query(['endpoint' => $webhookUrl, 'token' => $token]);
        }

        $settings = $integration?->settings ?? [];
        $mapSharingEnabled = ! empty($settings['map_sharing_enabled']);
        $mapDelaySeconds = (int) ($settings['map_delay_seconds'] ?? 0);
        $mapUrl = $mapSharingEnabled && $integration
            ? url("/map/{$user->twitch_id}")
            : null;

        return Inertia::render('settings/integrations/overlabels-mobile', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'webhook_url' => $webhookUrl,
                'deep_link' => $deepLink,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'speed_unit' => ($integration->settings ?? [])['speed_unit'] ?? 'kmh',
                'has_token' => ! empty($token),
                'map_sharing_enabled' => $mapSharingEnabled,
                'map_delay_seconds' => $mapDelaySeconds,
                'map_url' => $mapUrl,
            ] : [
                'connected' => false,
                'enabled' => false,
                'webhook_url' => null,
                'deep_link' => null,
                'last_received_at' => null,
                'speed_unit' => 'kmh',
                'has_token' => false,
                'map_sharing_enabled' => false,
                'map_delay_seconds' => 0,
                'map_url' => null,
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'speed_unit' => 'nullable|string|in:kmh,mph',
            'enabled' => 'nullable|boolean',
            'map_sharing_enabled' => 'nullable|boolean',
            'map_delay_seconds' => 'nullable|integer|in:0,60,120,300',
        ]);

        $isNew = ! ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'overlabels-mobile'],
            ['enabled' => true]
        );

        // Auto-generate token on first connect
        if ($isNew) {
            $integration->setCredentialsEncrypted([
                'token' => Str::random(32),
            ]);
        }

        $integration->settings = array_merge(
            $integration->settings ?? [],
            [
                'speed_unit' => $validated['speed_unit'] ?? 'kmh',
                'map_sharing_enabled' => (bool) ($validated['map_sharing_enabled'] ?? false),
                'map_delay_seconds' => (int) ($validated['map_delay_seconds'] ?? 0),
            ],
        );

        $integration->enabled = $isNew || (bool) ($validated['enabled'] ?? true);
        $integration->save();

        // Auto-provision controls (idempotent - also picks up newly added controls for existing integrations)
        $driver = ExternalServiceRegistry::driver('overlabels-mobile');
        $this->controlService->provision($user, $driver);

        return back()->with('success', 'Overlabels GPS integration saved.');
    }

    public function regenerateToken(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->first();

        if (! $integration) {
            return back()->with('error', 'Not connected.');
        }

        $integration->setCredentialsEncrypted([
            'token' => Str::random(32),
        ]);
        $integration->save();

        return back()->with('success', 'Token regenerated. Scan the new QR code in the app.');
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'overlabels-mobile');
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'Overlabels GPS disconnected.');
    }

    public function resetDistance(): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        // Reset distance controls to 0
        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'overlabels-mobile')
            ->where('key', 'gps_distance')
            ->where('source_managed', true)
            ->with('template')
            ->get();

        foreach ($controls as $control) {
            $control->update(['value' => '0']);

            $overlaySlug = $control->overlay_template_id
                ? ($control->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $overlaySlug,
                $control->broadcastKey(),
                $control->type,
                '0',
                $user->twitch_id,
            );
        }

        // Clear last position from settings
        $settings = $integration->settings ?? [];
        unset($settings['last_lat'], $settings['last_lng']);
        $integration->settings = $settings;
        $integration->save();

        return response()->json(['status' => 'ok']);
    }
}
