<?php

namespace App\Broadcasting;

use App\Services\BroadcastMeter;
use Illuminate\Contracts\Broadcasting\Broadcaster;

/**
 * Wraps the real (Reverb) broadcaster and counts every outbound broadcast
 * before delegating it. This is the one chokepoint every broadcast funnels
 * through - queued ShouldBroadcast and synchronous ShouldBroadcastNow alike -
 * so we meter usage here instead of at the ~30 scattered dispatch sites.
 *
 * Channel registration (Broadcast::channel), subscription auth, and everything
 * else pass straight through to the inner broadcaster via __call, so overlay
 * and dashboard auth behave exactly as before. Only broadcast() is decorated.
 */
class MeteredBroadcaster implements Broadcaster
{
    public function __construct(
        protected Broadcaster $inner,
        protected BroadcastMeter $meter,
    ) {}

    public function auth($request)
    {
        return $this->inner->auth($request);
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $this->inner->validAuthenticationResponse($request, $result);
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        // Count first, then deliver. recordChannels swallows its own failures,
        // so this can never block the broadcast below.
        $this->meter->recordChannels($channels);

        return $this->inner->broadcast($channels, $event, $payload);
    }

    public function resolveAuthenticatedUser($request)
    {
        return $this->inner->resolveAuthenticatedUser($request);
    }

    /**
     * Forward channel registration (channel/private/presence) and any other
     * broadcaster method to the wrapped driver unchanged.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->inner->{$method}(...$parameters);
    }
}
