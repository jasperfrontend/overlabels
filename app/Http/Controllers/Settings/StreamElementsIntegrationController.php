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
use Random\RandomException;

class StreamElementsIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamelements')
            ->first();

        $credentials = $integration?->getCredentialsDecrypted() ?? [];
        $settings = $integration->settings ?? [];

        return Inertia::render('settings/integrations/streamelements', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'test_mode' => $integration->test_mode,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'settings' => $settings,
                'has_jwt' => ! empty($credentials['jwt_token']),
                'donations_seed_set' => ! empty($settings['donations_seed_set']),
                'donations_seed_value' => $settings['donations_seed_value'] ?? null,
            ] : [
                'connected' => false,
                'enabled' => false,
                'test_mode' => false,
                'last_received_at' => null,
                'settings' => [],
                'has_jwt' => false,
                'donations_seed_set' => false,
                'donations_seed_value' => null,
            ],
        ]);
    }

    /**
     * Save the user's StreamElements JWT token.
     *
     * JWT is issued from the user's StreamElements dashboard (Account > Channels > Show
     * secrets > JWT Token). We store it encrypted and reuse it for the Socket.IO
     * `authenticate` handshake.
     * @throws RandomException
     */
    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'jwt_token' => 'required|string|max:4096',
            'enabled' => 'nullable|boolean',
        ]);

        $isNew = ! ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamelements')
            ->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'streamelements'],
            ['enabled' => true]
        );

        $existing = $integration->getCredentialsDecrypted();
        $listenerSecret = $existing['listener_secret'] ?? bin2hex(random_bytes(32));

        $integration->setCredentialsEncrypted([
            'jwt_token' => trim($validated['jwt_token']),
            'listener_secret' => $listenerSecret,
        ]);

        $integration->enabled = $isNew || ($validated['enabled'] ?? true);
        $integration->save();

        if ($isNew) {
            $driver = ExternalServiceRegistry::driver('streamelements');
            $this->controlService->provision($user, $driver);
        }

        return back()->with('success', 'StreamElements connected.');
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamelements')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate(['test_mode' => 'required|boolean']);

        $integration->update(['test_mode' => $validated['test_mode']]);

        if (! $validated['test_mode']) {
            $settings = $integration->settings ?? [];
            $seedValue = (string) ($settings['donations_seed_value'] ?? 0);

            $controls = OverlayControl::where('user_id', $user->id)
                ->where('source', 'streamelements')
                ->where('source_managed', true)
                ->with('template')
                ->get();

            foreach ($controls as $control) {
                $resetValue = match ($control->key) {
                    'donations_received' => $seedValue,
                    default => in_array($control->type, ['counter', 'number']) ? '0' : '',
                };

                $control->update(['value' => $resetValue]);

                $overlaySlug = $control->overlay_template_id
                    ? ($control->template?->slug ?? '')
                    : '';

                ControlValueUpdated::dispatch(
                    $overlaySlug,
                    $control->broadcastKey(),
                    $control->type,
                    $resetValue,
                    $user->twitch_id,
                );
            }
        }

        return response()->json(['test_mode' => $integration->test_mode]);
    }

    public function seedDonationCount(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamelements')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $settings = $integration->settings ?? [];

        if (! empty($settings['donations_seed_set'])) {
            return response()->json(['error' => 'Starting count has already been set.'], 403);
        }

        $validated = $request->validate([
            'initial_count' => 'required|integer|min:0|max:9999999',
        ]);

        OverlayControl::where('user_id', $user->id)
            ->where('source', 'streamelements')
            ->where('key', 'donations_received')
            ->where('source_managed', true)
            ->update(['value' => (string) $validated['initial_count']]);

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
            ->where('service', 'streamelements')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'streamelements');
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'StreamElements disconnected.');
    }
}
