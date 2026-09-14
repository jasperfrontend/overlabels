<?php

namespace App\Support;

use App\Models\ExternalIntegration;
use App\Services\External\ExternalServiceRegistry;

/**
 * How a donation service gets connected, per service: what the person has
 * to do and where the button goes. A product whose alert fires on several
 * services shows one row per service with the connect for it right there,
 * on the product page, so nobody is sent off to "make it work".
 *
 * Knowledge about the SERVICE, next to ServiceTestGuides for the same
 * reason: every product that takes a Ko-fi tip connects Ko-fi the same way.
 *
 * Four ways in:
 *   oauth  - one click, the service's consent screen, and back (Streamlabs, Fourthwall)
 *   token  - paste the token the service shows, then our link into the service (Ko-fi)
 *   secret - get our link first, paste it into the service, paste back the secret it reveals (Buy Me a Coffee)
 *   none   - one click makes the link, paste it into the service (Throne)
 */
final class ServiceConnections
{
    /** @var array<string, array{kind: string, route: string}> */
    private const array CONNECTS = [
        'streamlabs' => ['kind' => 'oauth', 'route' => 'settings.integrations.streamlabs.redirect'],
        'fourthwall' => ['kind' => 'oauth', 'route' => 'settings.integrations.fourthwall.redirect'],
        'kofi' => ['kind' => 'token', 'route' => 'settings.integrations.kofi.save'],
        'bmac' => ['kind' => 'secret', 'route' => 'settings.integrations.bmac.save'],
        'throne' => ['kind' => 'none', 'route' => 'settings.integrations.throne.connect'],
    ];

    public static function has(string $service): bool
    {
        return isset(self::CONNECTS[$service]);
    }

    /**
     * One row for the product page: where this service stands for this
     * person, and the one thing that connects it.
     *
     * `connected` is the wiring circuit's rule, not "a row exists": a row
     * the install created is not a connection until the service can reach
     * us, which for Ko-fi means the token is pasted and for Streamlabs that
     * the consent screen was clicked through. `received` says whether the
     * service has ever sent anything, which is the only proof that our link
     * was pasted on its side.
     *
     * `$returnTo` is where an OAuth round trip should come back to.
     *
     * @return array{service: string, label: string, kind: string, connected: bool, has_credential: bool, webhook_url: ?string, received: bool, connect_url: string, settings_url: string, test_guide: ?array<string, mixed>}|null
     */
    public static function for(string $service, ?ExternalIntegration $integration, string $returnTo): ?array
    {
        $connect = self::CONNECTS[$service] ?? null;
        if ($connect === null) {
            return null;
        }

        // Streamlabs pulls over a socket and Fourthwall registers its own
        // webhook through the API, so neither has a link to paste anywhere.
        $showsLink = $connect['kind'] !== 'oauth';

        return [
            'service' => $service,
            'label' => ExternalServiceRegistry::displayName($service),
            'kind' => $connect['kind'],
            'connected' => $integration !== null && $integration->enabled && $integration->isAuthenticated(),
            'has_credential' => $integration?->isAuthenticated() ?? false,
            'webhook_url' => $showsLink && $integration ? url("/api/webhooks/{$service}/{$integration->webhook_token}") : null,
            'received' => $integration?->last_received_at !== null,
            'connect_url' => $connect['kind'] === 'oauth'
                ? route($connect['route'], ['return_to' => $returnTo])
                : route($connect['route']),
            'settings_url' => route("settings.integrations.{$service}.show"),
            'test_guide' => ServiceTestGuides::for($service),
        ];
    }
}
