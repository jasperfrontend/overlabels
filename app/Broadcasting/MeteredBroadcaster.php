<?php

namespace App\Broadcasting;

use App\Services\BroadcastMeter;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Arr;
use Pusher\ApiErrorException;
use Pusher\Pusher;

/**
 * Wraps the real (Reverb) broadcaster and counts every outbound broadcast
 * before delegating it. This is the one chokepoint every broadcast funnels
 * through - queued ShouldBroadcast and synchronous ShouldBroadcastNow alike -
 * so we meter usage here instead of at the ~30 scattered dispatch sites.
 *
 * It is also the one place the app can learn what happened to a broadcast.
 * Reverb honours Pusher's `info=subscription_count` on the trigger call and
 * answers with the number of connections subscribed to each channel at the
 * moment it accepted the event. That is proof of delivery to N sockets -
 * never proof of paint - and it costs nothing: no presence channel, no
 * client message, the overlay still sends nothing back.
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

        if (! $this->inner instanceof PusherBroadcaster) {
            return $this->inner->broadcast($channels, $event, $payload);
        }

        $this->broadcastViaPusher($this->inner->getPusher(), $channels, (string) $event, $payload);

        return null;
    }

    /**
     * Mirror of PusherBroadcaster::broadcast() with one addition: ask for
     * `subscription_count` and hand the answer to the meter. Same socket
     * exclusion, same 100-channel chunking, same BroadcastException on an
     * API error - a failed delivery must still land in failed_jobs.
     *
     * @param  array<int, mixed>  $channels
     * @param  array<string, mixed>  $payload
     */
    protected function broadcastViaPusher(Pusher $pusher, array $channels, string $event, array $payload): void
    {
        $socket = Arr::pull($payload, 'socket');
        $parameters = ($socket !== null ? ['socket_id' => $socket] : []) + ['info' => 'subscription_count'];
        $names = array_map(fn ($channel) => (string) $channel, $channels);

        try {
            foreach (array_chunk($names, 100) as $chunk) {
                $result = $pusher->trigger($chunk, $event, $payload, $parameters);
                $this->meter->recordDelivery(self::subscriptionCounts($result), $event);
            }
        } catch (ApiErrorException $e) {
            throw new BroadcastException(
                sprintf('Pusher error: %s.', $e->getMessage())
            );
        }
    }

    /**
     * Pull `channel => subscription_count` out of Reverb's trigger response,
     * which is `{channels: {"<name>": {subscription_count: N}}}`. Anything
     * else (no info requested, an older server, an odd shape) yields [].
     *
     * @return array<string, int>
     */
    public static function subscriptionCounts(mixed $result): array
    {
        $channels = is_object($result) ? ($result->channels ?? null) : (is_array($result) ? ($result['channels'] ?? null) : null);
        if ($channels === null) {
            return [];
        }

        $counts = [];
        foreach ((array) $channels as $name => $info) {
            $info = (array) $info;
            if (isset($info['subscription_count'])) {
                $counts[(string) $name] = (int) $info['subscription_count'];
            }
        }

        return $counts;
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
