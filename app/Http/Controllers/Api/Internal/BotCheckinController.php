<?php

namespace App\Http\Controllers\Api\Internal;

use App\Contracts\StatefulExternalServiceDriver;
use App\Events\CheckinsUpdated;
use App\Events\ExternalEventStored;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Models\StreamSession;
use App\Models\User;
use App\Services\EventMeter;
use App\Services\External\ExternalAlertService;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\Geo\PlaceResolverService;
use App\Services\Geo\ResolvedPlace;
use App\Services\Location\GeoMath;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BotCheckinController extends Controller
{
    public function __construct(
        private readonly PlaceResolverService $resolver,
        private readonly ExternalControlService $controlService,
        private readonly ExternalAlertService $alertService,
    ) {}

    /**
     * Handle one `!checkin <place>` from chat, relayed by the bot.
     *
     * The bot is a thin relay: everything happens here. Place resolution is
     * the local gazetteer (PlaceResolverService), the pin upserts into
     * `checkins` (latest wins - a re-checkin moves the pin), and the event
     * then runs the SAME pipeline as ExternalWebhookController::handle steps
     * 7-11: store with dedup, meter, feed hook, control updates, alert
     * dispatch, last_received_at. Checkins have no public webhook route -
     * the driver's verifyRequest() refuses everything - so this endpoint is
     * the single door.
     *
     * Replies follow the followage convention: the returned `reply` string is
     * spoken inline by the bot; null means stay silent.
     */
    public function store(Request $request, string $login): JsonResponse
    {
        $data = $request->validate([
            'chatter_id' => 'required|string|max:32',
            'chatter_login' => 'required|string|max:64',
            'chatter_display_name' => 'required|string|max:64',
            'args' => 'nullable|string|max:200',
        ]);

        $user = $this->resolveUser($login);

        if (! $user) {
            return response()->json(['error' => 'channel not found'], 404);
        }

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'checkin')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return response()->json(['reply' => null]);
        }

        $args = trim($data['args'] ?? '');

        if ($args === '') {
            return response()->json(['reply' => 'Where are you? Try !checkin City, CC - like !checkin Rotterdam, NL']);
        }

        // Server-side cooldown backstop (the bot has its own command cooldown,
        // but this endpoint must defend itself). Silent: a cooldown reply per
        // spammed command would itself be spam.
        $settings = $integration->settings ?? [];
        $cooldown = max(5, (int) ($settings['cooldown_seconds'] ?? 30));

        if (! Cache::add("checkin:cooldown:{$user->id}:{$data['chatter_id']}", 1, $cooldown)) {
            return response()->json(['reply' => null]);
        }

        $place = $this->resolver->resolve($args);

        if (! $place) {
            return response()->json(['reply' => "Couldn't find that place. Try City, CC - like Rotterdam, NL"]);
        }

        $payload = $this->applyCheckin($user, $settings, $place, $data);

        $this->runPipeline($user, $integration, $payload);

        $reply = "{$data['chatter_display_name']} checked in from {$place->label()}!";

        if ($payload['distance'] !== null && $payload['distance'] >= 1) {
            $reply .= ' That is '.number_format((float) $payload['distance']).' km away.';
        }

        return response()->json(['reply' => $reply]);
    }

    /**
     * Upsert the pin (latest wins) and assemble the enriched payload the
     * checkin driver normalizes. All derived facts are computed here, once:
     * the driver maps them onto controls and never re-derives.
     *
     * @return array<string, mixed>
     */
    private function applyCheckin(User $user, array $settings, ResolvedPlace $place, array $data): array
    {
        $distanceKm = null;

        if (isset($settings['home_lat'], $settings['home_lng'])) {
            $distanceKm = round(GeoMath::haversineDistance(
                (float) $settings['home_lat'],
                (float) $settings['home_lng'],
                $place->lat,
                $place->lng,
            ), 1);
        }

        $existing = Checkin::where('user_id', $user->id)
            ->where('chatter_twitch_id', $data['chatter_id'])
            ->first();

        // "New this stream" is what the per-stream counter counts: a viewer's
        // first pin since this stream started. A pin move within the stream
        // increments nothing. Off-stream, only a brand new pin counts (the
        // counter resets at go-live anyway).
        $session = StreamSession::activeFor($user);
        $newThisStream = $existing === null
            || ($session !== null && $existing->checked_in_at->lt($session->started_at));

        $now = now();
        $checkin = Checkin::updateOrCreate(
            ['user_id' => $user->id, 'chatter_twitch_id' => $data['chatter_id']],
            [
                'chatter_login' => strtolower($data['chatter_login']),
                'chatter_display_name' => $data['chatter_display_name'],
                'place_label' => $place->label(),
                'country_code' => $place->countryCode,
                'lat' => $place->lat,
                'lng' => $place->lng,
                'distance_km' => $distanceKm,
                'checked_in_at' => $now,
            ],
        );

        $pinLifetime = ($settings['pin_lifetime'] ?? 'per_stream');

        CheckinsUpdated::dispatch(
            $user->twitch_id,
            $checkin->toPinArray(),
            Checkin::windowCountFor($user, $pinLifetime),
        );

        return [
            'type' => 'checkin',
            'chatter_id' => $data['chatter_id'],
            'chatter_login' => strtolower($data['chatter_login']),
            'chatter_display_name' => $data['chatter_display_name'],
            'place_label' => $place->label(),
            'place_name' => $place->name,
            'country_code' => $place->countryCode,
            'country_name' => $place->countryName,
            'lat' => $place->lat,
            'lng' => $place->lng,
            // Kilometers - the |distance: pipe's input unit. Only the DB
            // column keeps the unit in its name, as documentation at rest.
            'distance' => $distanceKm,
            'at' => $now->getTimestamp(),
            'pin_created' => $existing === null,
            'new_this_stream' => $newThisStream,
            'total_pins' => Checkin::where('user_id', $user->id)->count(),
        ];
    }

    /**
     * ExternalWebhookController::handle steps 7-11 for a controller-built
     * payload: normalize, store with dedup, meter, feed hook, controls,
     * alert, last_received_at. The stored event is passed to the alert
     * dispatch so the delivery ledger sees checkin alerts, and the
     * controls_updated / alert_dispatched flags are written like the webhook
     * path writes them.
     */
    private function runPipeline(User $user, ExternalIntegration $integration, array $payload): void
    {
        $driver = ExternalServiceRegistry::driver('checkin');
        $normalizedEvent = $driver->normalizeEvent($payload, 'checkin');

        try {
            // The nested transaction is a savepoint: on the unique violation
            // Postgres rolls back to it instead of aborting the surrounding
            // transaction (which is how this path behaves under test).
            $storedEvent = DB::transaction(fn () => ExternalEvent::create([
                'user_id' => $user->id,
                'service' => 'checkin',
                'event_type' => 'checkin',
                'message_id' => $normalizedEvent->getMessageId(),
                'raw_payload' => $normalizedEvent->getRaw(),
                'normalized_payload' => $normalizedEvent->getTemplateTags(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Same viewer, same second: the pin already moved above, the rest
            // of the pipeline has nothing new to say. Intended.
            return;
        }

        app(EventMeter::class)->record($user->id);

        ExternalEventStored::dispatch($user->id, 'checkin', 'checkin', $normalizedEvent->getTemplateTags());

        $controlUpdates = $driver->getControlUpdates($normalizedEvent);

        if ($driver instanceof StatefulExternalServiceDriver) {
            $driver->beforeControlUpdates($integration, $normalizedEvent, $controlUpdates);
        }

        if (! empty($controlUpdates)) {
            $this->controlService->applyUpdates($user, 'checkin', $controlUpdates);
            $storedEvent->update(['controls_updated' => true]);
        }

        if ($this->alertService->dispatch($normalizedEvent, $user, $storedEvent)) {
            $storedEvent->update(['alert_dispatched' => true]);
        }

        $integration->update(['last_received_at' => now()]);
    }

    private function resolveUser(string $login): ?User
    {
        $login = strtolower($login);

        return User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
    }
}
