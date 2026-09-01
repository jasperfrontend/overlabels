<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\ExternalIntegration;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\Geo\PlaceResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-party, non-donation integration - like GPS it deliberately does NOT
 * extend DonationIntegrationController: no test mode, no donation seed, no
 * webhook URL (the only entry point is the bot's internal endpoint).
 */
class CheckinIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
        private readonly PlaceResolverService $resolver,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'checkin')
            ->first();

        $settings = $integration?->settings ?? [];

        return Inertia::render('settings/integrations/checkin', [
            'integration' => [
                'connected' => $integration !== null,
                'enabled' => (bool) ($integration?->enabled ?? false),
                'pin_lifetime' => $settings['pin_lifetime'] ?? 'per_stream',
                'home_place_label' => $settings['home_place_label'] ?? null,
                'cooldown_seconds' => (int) ($settings['cooldown_seconds'] ?? 30),
                'last_received_at' => $integration?->last_received_at?->toIso8601String(),
                'total_pins' => $integration ? Checkin::where('user_id', $user->id)->count() : 0,
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'pin_lifetime' => 'required|string|in:per_stream,persistent',
            'home_place' => 'nullable|string|max:120',
            'cooldown_seconds' => 'nullable|integer|min:5|max:600',
        ]);

        $home = null;
        $homeQuery = trim($validated['home_place'] ?? '');

        if ($homeQuery !== '') {
            $home = $this->resolver->resolve($homeQuery);

            if (! $home) {
                return back()->withErrors([
                    'home_place' => 'Could not find that place. Try City, CC - like Rotterdam, NL.',
                ]);
            }
        }

        $isNew = ! ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'checkin')
            ->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'checkin'],
            ['enabled' => true]
        );

        $settings = array_merge($integration->settings ?? [], [
            'pin_lifetime' => $validated['pin_lifetime'],
            'cooldown_seconds' => (int) ($validated['cooldown_seconds'] ?? 30),
        ]);

        // Home is what distance_km measures against. Set it, move it, or clear
        // it - already-stamped pins keep the distance they were made with.
        if ($home) {
            $settings['home_place_label'] = $home->label();
            $settings['home_lat'] = $home->lat;
            $settings['home_lng'] = $home->lng;
        } else {
            unset($settings['home_place_label'], $settings['home_lat'], $settings['home_lng']);
        }

        $integration->settings = $settings;
        $integration->enabled = $isNew || (bool) ($validated['enabled'] ?? true);
        $integration->save();

        // Idempotent, on EVERY save - the connectIntegration invariant that
        // makes "connect a service, get its controls" true.
        $this->controlService->provision($user, ExternalServiceRegistry::driver('checkin'));

        return back()->with('success', 'Chat Checkin integration saved.');
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'checkin')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'checkin');
            $integration->delete();
        }

        // Pins in `checkins` survive a disconnect on purpose: reconnecting
        // brings the map back instead of punishing a settings round trip.
        return redirect()->route('settings.integrations.index')
            ->with('success', 'Chat Checkin disconnected.');
    }
}
