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
use Inertia\Inertia;
use Inertia\Response;

class GpsLoggerIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gpslogger')
            ->first();

        $webhookUrl = $integration
            ? url("/api/webhooks/gpslogger/{$integration->webhook_token}")
            : null;

        $settings = $integration->settings ?? [];

        return Inertia::render('settings/integrations/gpslogger', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'webhook_url' => $webhookUrl,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'speed_unit' => $settings['speed_unit'] ?? 'kmh',
                'has_token' => ! empty($integration->getCredentialsDecrypted()['token']),
            ] : [
                'connected' => false,
                'enabled' => false,
                'webhook_url' => null,
                'last_received_at' => null,
                'speed_unit' => 'kmh',
                'has_token' => false,
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'token' => 'required|string|max:255',
            'speed_unit' => 'nullable|string|in:kmh,mph',
            'enabled' => 'nullable|boolean',
        ]);

        $isNew = ! ExternalIntegration::where('user_id', $user->id)->where('service', 'gpslogger')->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'gpslogger'],
            ['enabled' => true]
        );

        $integration->setCredentialsEncrypted([
            'token' => $validated['token'],
        ]);

        $integration->settings = array_merge(
            $integration->settings ?? [],
            ['speed_unit' => $validated['speed_unit'] ?? 'kmh'],
        );

        $integration->enabled = $isNew ? true : (bool) ($validated['enabled'] ?? true);
        $integration->save();

        // Auto-provision controls on first connect
        if ($isNew) {
            $driver = ExternalServiceRegistry::driver('gpslogger');
            $this->controlService->provision($user, $driver);
        }

        return back()->with('success', 'GPSLogger integration saved.');
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gpslogger')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'gpslogger');
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'GPSLogger disconnected.');
    }

    public function resetDistance(): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gpslogger')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        // Reset distance controls to 0
        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'gpslogger')
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
