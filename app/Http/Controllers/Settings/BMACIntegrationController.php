<?php

namespace App\Http\Controllers\Settings;

use App\Events\ControlValueUpdated;
use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Services\External\ExternalControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BMACIntegrationController extends Controller
{
    private const string SERVICE_KEY = 'bmac';

    private const array SUPPORTED_EVENTS = [
        'donation',
        'commission',
        'extra',
        'membership',
        'recurring',
        'wishlist',
    ];

    public function __construct(
        private readonly ExternalControlService $controlService,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE_KEY)
            ->first();

        $webhookUrl = $integration
            ? url('/api/webhooks/'.self::SERVICE_KEY.'/'.$integration->webhook_token)
            : null;

        $credentials = $integration?->getCredentialsDecrypted() ?? [];
        $settings = $integration->settings ?? [];

        return Inertia::render('settings/integrations/bmac', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'test_mode' => $integration->test_mode,
                'webhook_url' => $webhookUrl,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'settings' => $settings,
                'has_secret' => ! empty($credentials['webhook_secret']),
                'donations_seed_set' => ! empty($settings['donations_seed_set']),
                'donations_seed_value' => $settings['donations_seed_value'] ?? null,
            ] : [
                'connected' => false,
                'enabled' => false,
                'test_mode' => false,
                'webhook_url' => null,
                'last_received_at' => null,
                'settings' => [],
                'has_secret' => false,
                'donations_seed_set' => false,
                'donations_seed_value' => null,
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // BMAC's webhook secret is generated AFTER you paste the Overlabels
        // webhook URL into BMAC, so the first save creates the integration
        // (and thus the webhook_token URL) with no secret. The user comes back
        // with the secret on a second save.
        $validated = $request->validate([
            'webhook_secret' => 'nullable|string|max:512',
            'enabled_events' => 'nullable|array',
            'enabled_events.*' => 'string|in:'.implode(',', self::SUPPORTED_EVENTS),
            'enabled' => 'nullable|boolean',
        ]);

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => self::SERVICE_KEY],
            ['enabled' => false]
        );

        $existingCredentials = $integration->getCredentialsDecrypted();
        $hadSecret = ! empty($existingCredentials['webhook_secret']);
        $newSecret = $validated['webhook_secret'] ?? null;

        if ($newSecret !== null && $newSecret !== '') {
            $integration->setCredentialsEncrypted(['webhook_secret' => $newSecret]);
        }

        // Merge so one-time flags (donations_seed_set) survive a re-save.
        $integration->settings = array_merge(
            $integration->settings ?? [],
            ['enabled_events' => $validated['enabled_events'] ?? self::SUPPORTED_EVENTS],
        );

        // Only enable once a secret exists - until then, incoming webhooks
        // would 403 anyway and BMAC would disable the webhook on us.
        $hasSecret = $hadSecret || ($newSecret !== null && $newSecret !== '');
        $integration->enabled = $hasSecret && ($validated['enabled'] ?? true);
        $integration->save();

        $message = $hasSecret
            ? 'Buy Me a Coffee integration saved.'
            : 'Webhook URL generated. Copy it into your BMAC webhook, then come back here with the secret BMAC shows you.';

        return back()->with('success', $message);
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE_KEY)
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
                ->where('source', self::SERVICE_KEY)
                ->where('source_managed', true)
                ->with('template')
                ->get();

            foreach ($controls as $control) {
                $resetValue = match ($control->key) {
                    'donations_received' => $seedValue,
                    default => in_array($control->type, ['counter', 'number'], true) ? '0' : '',
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
            ->where('service', self::SERVICE_KEY)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $settings = $integration->settings ?? [];

//        if (! empty($settings['donations_seed_set'])) {
//            return response()->json(['error' => 'Starting count has already been set.'], 403);
//        }

        $validated = $request->validate([
            'initial_count' => 'required|integer|min:0|max:9999999',
        ]);

        OverlayControl::where('user_id', $user->id)
            ->where('source', self::SERVICE_KEY)
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
            ->where('service', self::SERVICE_KEY)
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, self::SERVICE_KEY);
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'Buy Me a Coffee disconnected.');
    }
}
