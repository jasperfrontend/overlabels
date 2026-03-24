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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StreamLabsIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamlabs')
            ->first();

        $settings = $integration->settings ?? [];

        return Inertia::render('settings/integrations/streamlabs', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'test_mode' => $integration->test_mode,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'settings' => $settings,
                'donations_seed_set' => ! empty($settings['donations_seed_set']),
                'donations_seed_value' => $settings['donations_seed_value'] ?? null,
            ] : [
                'connected' => false,
                'enabled' => false,
                'test_mode' => false,
                'last_received_at' => null,
                'settings' => [],
                'donations_seed_set' => false,
                'donations_seed_value' => null,
            ],
        ]);
    }

    /**
     * Redirect the user to StreamLabs OAuth authorization page.
     */
    public function redirect(): RedirectResponse
    {
        $params = http_build_query([
            'client_id' => config('services.streamlabs.client_id'),
            'redirect_uri' => url('/auth/callback/streamlabs'),
            'response_type' => 'code',
            'scope' => 'socket.token donations.read donations.create',
        ]);

        return redirect("https://www.streamlabs.com/api/v1.0/authorize?{$params}");
    }

    /**
     * Handle the OAuth callback from StreamLabs.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (! $code) {
            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'StreamLabs authorization was cancelled.');
        }

        // Exchange authorization code for access token
        $tokenResponse = Http::post('https://streamlabs.com/api/v1.0/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.streamlabs.client_id'),
            'client_secret' => config('services.streamlabs.client_secret'),
            'redirect_uri' => url('/auth/callback/streamlabs'),
            'code' => $code,
        ]);

        if (! $tokenResponse->ok()) {
            Log::error('StreamLabs token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'Failed to connect to StreamLabs. Please try again.');
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (! $accessToken) {
            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'StreamLabs did not return an access token.');
        }

        // Fetch socket token for the Socket.IO listener
        $socketResponse = Http::withToken($accessToken)
            ->get('https://streamlabs.com/api/v1.0/socket/token');

        if (! $socketResponse->ok()) {
            Log::error('StreamLabs socket token fetch failed', [
                'status' => $socketResponse->status(),
                'body' => $socketResponse->body(),
            ]);

            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'Connected to StreamLabs but failed to get socket token.');
        }

        $socketToken = $socketResponse->json('socket_token');

        // Generate a per-integration secret for webhook verification
        $listenerSecret = bin2hex(random_bytes(32));

        $user = auth()->user();

        $isNew = ! ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamlabs')
            ->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'streamlabs'],
            ['enabled' => true]
        );

        $integration->setCredentialsEncrypted([
            'access_token' => $accessToken,
            'socket_token' => $socketToken,
            'listener_secret' => $listenerSecret,
        ]);

        $integration->enabled = true;
        $integration->save();

        // Auto-provision controls on first connection
        if ($isNew) {
            $driver = ExternalServiceRegistry::driver('streamlabs');
            $this->controlService->provision($user, $driver);
        }

        return redirect()->route('settings.integrations.streamlabs.show')
            ->with('success', 'StreamLabs connected successfully.');
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'streamlabs')
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate(['test_mode' => 'required|boolean']);

        $integration->update(['test_mode' => $validated['test_mode']]);

        // When test mode is turned OFF, reset donations_received to seed value (or 0)
        if (! $validated['test_mode']) {
            $settings = $integration->settings ?? [];
            $resetValue = (string) ($settings['donations_seed_value'] ?? 0);

            $controls = OverlayControl::where('user_id', $user->id)
                ->where('source', 'streamlabs')
                ->where('key', 'donations_received')
                ->where('source_managed', true)
                ->with('template')
                ->get();

            foreach ($controls as $control) {
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
            ->where('service', 'streamlabs')
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
            ->where('source', 'streamlabs')
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
            ->where('service', 'streamlabs')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'streamlabs');
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'StreamLabs disconnected.');
    }
}
