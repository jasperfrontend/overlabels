<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\TowerBlock;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\Tower\TowerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-party, non-donation integration - like GPS and checkin it
 * deliberately does NOT extend DonationIntegrationController: no test mode,
 * no donation seed, no webhook URL (the only entry point is the bot's
 * internal endpoint).
 */
class TowerIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalControlService $controlService,
        private readonly TowerService $tower,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'tower')
            ->first();

        $settings = $integration?->settings ?? [];

        return Inertia::render('settings/integrations/tower', [
            'integration' => [
                'connected' => $integration !== null,
                'enabled' => (bool) ($integration?->enabled ?? false),
                'tower_lifetime' => $settings['tower_lifetime'] ?? 'per_stream',
                'cooldown_seconds' => (int) ($settings['cooldown_seconds'] ?? 5),
                'last_received_at' => $integration?->last_received_at?->toIso8601String(),
                'height' => $integration ? TowerBlock::heightFor($user) : 0,
                'record' => $integration ? $this->tower->currentRecord($user) : 0,
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'tower_lifetime' => 'required|string|in:per_stream,persistent',
            'cooldown_seconds' => 'nullable|integer|min:1|max:600',
        ]);

        $isNew = ! ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'tower')
            ->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => 'tower'],
            ['enabled' => true]
        );

        $integration->settings = array_merge($integration->settings ?? [], [
            'tower_lifetime' => $validated['tower_lifetime'],
            'cooldown_seconds' => (int) ($validated['cooldown_seconds'] ?? 5),
        ]);
        $integration->enabled = $isNew || (bool) ($validated['enabled'] ?? true);
        $integration->save();

        // Idempotent, on EVERY save - the connectIntegration invariant that
        // makes "connect a service, get its controls" true.
        $this->controlService->provision($user, ExternalServiceRegistry::driver('tower'));

        return back()->with('success', 'Chat Tower integration saved.');
    }

    /**
     * Knock the standing tower down by hand. The record, the counters and
     * the last_* controls stay; only the blocks and the three standing
     * controls go.
     */
    public function reset(): RedirectResponse
    {
        $this->tower->clear(auth()->user());

        return back()->with('success', 'Tower cleared.');
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'tower')
            ->first();

        if ($integration) {
            $this->controlService->deprovision($user, 'tower');
            $integration->delete();
        }

        // The standing blocks survive a disconnect on purpose: reconnecting
        // brings the tower back instead of punishing a settings round trip.
        return redirect()->route('settings.integrations.index')
            ->with('success', 'Chat Tower disconnected.');
    }
}
