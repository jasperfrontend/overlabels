<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\External\ExternalServiceRegistry;
use App\Services\Recipes\RecipeCatalog;
use App\Services\UserEventSubManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(private readonly UserEventSubManager $eventSubManager) {}

    public function index(RecipeCatalog $catalog): Response
    {
        $user = auth()->user();

        $integrations = ExternalIntegration::where('user_id', $user->id)
            ->get()
            ->keyBy('service');

        // The settings page URL slug for a service. For most services the
        // canonical key matches the URL, but `gps` keeps the historical
        // `overlabels-mobile` URL so saved bookmarks and the mobile app's
        // deep-link target keep working.
        $urlSlugs = ['gps' => 'overlabels-mobile'];

        $productOf = $this->productIntegrations($catalog);

        $services = array_map(function (string $service) use ($integrations, $urlSlugs, $productOf) {
            $integration = $integrations->get($service);

            return [
                'key' => $service,
                'url_slug' => $urlSlugs[$service] ?? $service,
                'name' => $this->serviceName($service),
                'product' => $productOf[$service] ?? null,
                'connected' => (bool) $integration,
                'enabled' => $integration?->enabled ?? false,
                'test_mode' => $integration?->test_mode ?? false,
                'last_received_at' => $integration?->last_received_at?->toIso8601String(),
            ];
        }, ExternalServiceRegistry::services());

        // Registry order is the order drivers were added in, which is what
        // put Chat Tower at the bottom of the page. The page wants what needs
        // attention on top: anything not connected first, then A-Z by name.
        usort($services, function (array $a, array $b): int {
            if ($a['connected'] !== $b['connected']) {
                return $a['connected'] ? 1 : -1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $eventsub = $this->eventSubState($user);

        return Inertia::render('settings/integrations/index', [
            'services' => array_values($services),
            // The list row needs a one-line status, not the whole event list.
            'eventsub' => [
                'connected' => $eventsub['connected'],
                'active_count' => $eventsub['active_count'],
                'supported_count' => count($eventsub['supported_events']),
            ],
            'bot' => [
                'enabled' => (bool) $user->bot_enabled,
                // Enabled-only, to match the Twitch row's active event count:
                // both numbers answer "what will actually respond in chat".
                // The command map filters on `enabled` too.
                'command_count' => BotCommand::where('user_id', $user->id)->where('enabled', true)->count(),
                'alias_count' => BotAlias::where('user_id', $user->id)->where('enabled', true)->count(),
            ],
        ]);
    }

    /**
     * Twitch Alerts has a settings page like every other integration, so the
     * list can stay one flat list of rows. Everything that talks to Twitch -
     * connect, reconnect, the test cheer, the event list - lives there.
     */
    public function showTwitch(): Response
    {
        return Inertia::render('settings/integrations/twitch', [
            'eventsub' => $this->eventSubState(auth()->user()),
        ]);
    }

    /**
     * @return array{connected: bool, connected_at: ?string, subscription_count: int, active_count: int, supported_events: list<array{key: string, label: string, active: bool}>}
     */
    private function eventSubState(User $user): array
    {
        $subscriptions = UserEventsubSubscription::where('user_id', $user->id)->get();
        $eventLabels = UserEventSubManager::getSupportedEventLabels();

        return [
            'connected' => $user->eventsub_connected_at !== null,
            'connected_at' => $user->eventsub_connected_at?->toIso8601String(),
            'subscription_count' => $subscriptions->count(),
            'active_count' => $subscriptions->where('status', 'enabled')->count(),
            'supported_events' => array_map(
                fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'active' => $subscriptions->where('event_type', $key)->where('status', 'enabled')->isNotEmpty(),
                ],
                $eventLabels,
                array_keys($eventLabels),
            ),
        ];
    }

    /**
     * Dispatch EventSub setup to the queue so Twitch challenge POSTs can hit a
     * free web worker while the queue worker makes the outbound Helix calls.
     * Running this inline on the web worker starves the challenges and leaves
     * most subscriptions in webhook_callback_verification_failed state.
     * FinalizeEventSubSetup fires EventSubSetupCompleted on alerts.{twitch_id}
     * with the created/failed counts ~15s after the creates, once Twitch's
     * challenges have settled and statuses have been re-verified.
     */
    public function connectEventSub(Request $request): JsonResponse
    {
        $user = $request->user();

        SetupUserEventSubSubscriptions::dispatch($user, true);

        return response()->json([
            'success' => true,
            'message' => 'Setting up Twitch subscriptions. This takes about 30 seconds; the page will update automatically when done.',
        ], 202);
    }

    private function serviceName(string $key): string
    {
        return ExternalServiceRegistry::displayName($key);
    }

    /**
     * Which integrations belong to an Overlabels product, keyed by service
     * with the product slug as the value. Read from the listed product
     * manifests' `requires_integrations`, so a product that ships with an
     * integration is marked on the settings page by the same file that
     * installs it - there is no second list to keep in step.
     *
     * @return array<string, string>
     */
    private function productIntegrations(RecipeCatalog $catalog): array
    {
        $productOf = [];

        foreach ($catalog->listed() as $slug => $manifest) {
            foreach ($manifest['requires_integrations'] ?? [] as $service) {
                $productOf[$service] = $slug;
            }
        }

        return $productOf;
    }
}
