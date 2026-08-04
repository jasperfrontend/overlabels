<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared settings surface for the donation-family integrations: Ko-fi,
 * Streamlabs, Fourthwall, Buy Me a Coffee and Throne.
 *
 * These five differ in exactly one thing - how you authenticate, which is a
 * pasted token, an OAuth round trip, or nothing at all. Everything after that
 * point is identical, and used to be five copies of the same four methods. The
 * copies had drifted: `seedDonationCount()` was byte-identical in Ko-fi and
 * Streamlabs but spelled its service key three different ways across the other
 * three (`self::SERVICE_KEY`, `self::SERVICE`, and a bare literal).
 *
 * The drift that mattered was provisioning. `provision()` was called from three
 * controllers and not the other two, so connecting Ko-fi, BMAC or Throne gave
 * you a working webhook, verified signatures, events landing in the table, and
 * no controls at all to read them from. It is called from one place now, so
 * "connect a service, get its controls" is true by construction rather than by
 * five controllers remembering to do it.
 *
 * Overlabels GPS deliberately does not extend this: it carries location and
 * device telemetry rather than donations, shares none of the six donation keys,
 * and has no test mode or seed amount to speak of.
 */
abstract class DonationIntegrationController extends Controller
{
    public function __construct(
        protected readonly ExternalControlService $controlService,
    ) {}

    /**
     * The ExternalServiceRegistry key. The single spelling of it, per service.
     */
    abstract protected function service(): string;

    /**
     * Human-facing name, used in flash messages. Comes from the registry so a
     * rename lands everywhere at once.
     */
    protected function displayName(): string
    {
        return ExternalServiceRegistry::displayName($this->service());
    }

    /**
     * Inertia page. Every settings page is named after its service key.
     */
    protected function page(): string
    {
        return 'settings/integrations/'.$this->service();
    }

    /**
     * Whether the user needs to see their inbound webhook URL to paste it into
     * the service. False for the OAuth services: Streamlabs pulls over a socket,
     * and Fourthwall registers its own webhook through the API.
     */
    protected function showsWebhookUrl(): bool
    {
        return true;
    }

    /**
     * Boolean "is this credential present?" props for the settings page, as
     * `prop name => credential key`. The value itself is never sent.
     *
     * @return array<string, string>
     */
    protected function credentialFlags(): array
    {
        return [];
    }

    /**
     * Extra work before the integration row is deleted, e.g. deregistering a
     * webhook on the remote side.
     */
    protected function beforeDisconnect(ExternalIntegration $integration): void {}

    public function show(): Response
    {
        $integration = $this->integration();
        $settings = $integration?->settings ?? [];
        $credentials = $integration?->getCredentialsDecrypted() ?? [];

        $flags = [];
        foreach ($this->credentialFlags() as $prop => $credentialKey) {
            $flags[$prop] = ! empty($credentials[$credentialKey]);
        }

        $webhook = $this->showsWebhookUrl()
            ? ['webhook_url' => $integration
                ? url("/api/webhooks/{$this->service()}/{$integration->webhook_token}")
                : null]
            : [];

        return Inertia::render($this->page(), [
            'integration' => [
                'connected' => (bool) $integration,
                'enabled' => (bool) $integration?->enabled,
                'test_mode' => (bool) $integration?->test_mode,
                'last_received_at' => $integration?->last_received_at?->toIso8601String(),
                'settings' => $settings,
                'donations_seed_set' => ! empty($settings['donations_seed_set']),
                'donations_seed_value' => $settings['donations_seed_value'] ?? null,
                ...$webhook,
                ...$flags,
            ],
        ]);
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $integration = $this->integration();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate(['test_mode' => 'required|boolean']);

        $integration->update(['test_mode' => $validated['test_mode']]);

        // Turning test mode OFF resets every service-managed control back to its
        // default, re-applying the seeded starting amount if one was saved, so a
        // streamer's donation goal survives the toggle.
        if (! $validated['test_mode']) {
            $settings = $integration->settings ?? [];
            $this->controlService->resetServiceManagedControls(
                auth()->user(),
                $this->service(),
                isset($settings['donations_seed_value']) ? (string) $settings['donations_seed_value'] : null,
            );
        }

        return response()->json(['test_mode' => $integration->test_mode]);
    }

    public function seedDonationCount(Request $request): JsonResponse
    {
        $integration = $this->integration();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        // Re-seeding is allowed on purpose. An earlier version rejected a second
        // seed with a 403, which meant one typo permanently wrong.
        //
        // This seeds `total_received`, which is an amount and not a count, so
        // fractional values are the normal case: a streamer already sitting on
        // 65.35 has to be able to say exactly that. The frontend settles the
        // decimal separator, so what arrives here is always dot-separated.
        $validated = $request->validate([
            'initial_count' => 'required|numeric|decimal:0,2|min:0|max:9999999',
        ]);

        $seedValue = $this->controlService->normalizeSeedAmount($validated['initial_count']);

        $this->controlService->seedTotalReceived(auth()->user(), $this->service(), $seedValue);

        $integration->settings = array_merge($integration->settings ?? [], [
            'donations_seed_set' => true,
            'donations_seed_value' => $seedValue,
        ]);
        $integration->save();

        return response()->json([
            'donations_seed_set' => true,
            'donations_seed_value' => $seedValue,
        ]);
    }

    public function disconnect(): RedirectResponse
    {
        $integration = $this->integration();

        if ($integration) {
            $this->beforeDisconnect($integration);
            $this->controlService->deprovision(auth()->user(), $this->service());
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', $this->displayName().' disconnected.');
    }

    /**
     * This user's integration row for this service, if any.
     */
    protected function integration(?User $user = null): ?ExternalIntegration
    {
        return ExternalIntegration::where('user_id', ($user ?? auth()->user())->id)
            ->where('service', $this->service())
            ->first();
    }

    /**
     * Create-or-fetch the integration and provision its controls.
     *
     * Every connect flow routes through here, which is what makes "connect a
     * service, get its controls" structurally true. `provision()` is idempotent,
     * so calling it on every connect rather than only the first also means a
     * driver that gains a control later picks it up on the next reconnect
     * instead of silently never appearing for existing users.
     *
     * @param  array<string, mixed>  $attributes  applied only on create
     */
    protected function connectIntegration(User $user, array $attributes = ['enabled' => true]): ExternalIntegration
    {
        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => $this->service()],
            $attributes,
        );

        $this->controlService->provision($user, ExternalServiceRegistry::driver($this->service()));

        return $integration;
    }
}
