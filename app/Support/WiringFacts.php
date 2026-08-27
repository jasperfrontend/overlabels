<?php

namespace App\Support;

use App\Enums\DeliveryOutcome;
use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\StreamState;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\BotPresence;
use App\Services\BroadcastMeter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The query pass behind /wiring. Produces one subject per thing that can be
 * evaluated, each carrying a state per wire plus human-readable context.
 *
 * Deliberately NOT cached. The whole reason a wire is a query is that it
 * cannot go stale; a TTL would reintroduce exactly the drift that storing
 * completion rows would have.
 */
final class WiringFacts
{
    /**
     * A list slug is snake_case, and a read tag always closes with at least
     * `]`, so a trailing non-slug character is enough to stop list `q` from
     * matching `c:list:quotes`. Without the boundary every short slug would
     * report itself as read by whatever longer list happens to share a prefix.
     */
    private const READ_TAG_BOUNDARY = '[^a-z0-9_]';

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function for(User $user): array
    {
        return [
            'alerts' => [self::alertsSubject($user)],
            'bot' => [self::botSubject($user)],
            'lists' => self::listSubjects($user),
        ];
    }

    /**
     * The delivery path for alerts, read in the present tense from state the
     * app already holds: the Twitch login on the account, the EventSub
     * subscriptions Twitch reports, the delivery ledger's most recent scored
     * row, and the last connection count Reverb answered with. None of it is
     * stored for this page; all of it exists because something else needed it.
     *
     * With no alert set up, none of these questions arise.
     *
     * @return array<string, mixed>
     */
    private static function alertsSubject(User $user): array
    {
        $mappingCount = EventTemplateMapping::where('user_id', $user->id)
            ->where('enabled', true)
            ->whereNotNull('template_id')
            ->count()
            + ExternalEventTemplateMapping::where('user_id', $user->id)
                ->where('enabled', true)
                ->whereNotNull('overlay_template_id')
                ->count();

        if ($mappingCount === 0) {
            return [
                'key' => 'account',
                'label' => 'Your alerts',
                'context' => [],
                'states' => [
                    'alerts.token_valid' => WiringCatalog::NOT_APPLICABLE,
                    'alerts.subscribed' => WiringCatalog::NOT_APPLICABLE,
                    'alerts.delivering' => WiringCatalog::NOT_APPLICABLE,
                    'alerts.overlay_listening' => WiringCatalog::NOT_APPLICABLE,
                ],
            ];
        }

        $context = [$mappingCount.' '.($mappingCount === 1 ? 'alert' : 'alerts').' set up'];

        // The Twitch login we hold. The webhook path builds an alert with the
        // account's access token as stored - nothing refreshes it there - so
        // an expired token is not a stale timestamp, it is the next alert
        // failing. Opening the dashboard refreshes it; a refresh that fails
        // sends the streamer back through Twitch, which is the fix.
        $tokenValid = $user->token_expires_at !== null && $user->token_expires_at->isFuture();
        if (! $tokenValid) {
            $context[] = $user->token_expires_at === null
                ? 'No Twitch login stored'
                : 'Twitch login expired '.$user->token_expires_at->diffForHumans();
        }

        // What Twitch says about our subscriptions, as of the last challenge,
        // revocation or hourly verify.
        $subscriptions = UserEventsubSubscription::where('user_id', $user->id)
            ->selectRaw("count(*) filter (where status = 'enabled') as active")
            ->selectRaw("count(*) filter (where status <> 'enabled') as failed")
            ->first();
        $active = (int) ($subscriptions->active ?? 0);
        $failed = (int) ($subscriptions->failed ?? 0);
        $subscribed = $user->eventsub_connected_at !== null && $active > 0 && $failed === 0;
        $context[] = match (true) {
            $user->eventsub_connected_at === null => 'Not connected to Twitch events',
            $failed > 0 => $failed.' of '.($active + $failed).' Twitch subscriptions need repair',
            default => $active.' Twitch subscriptions active',
        };

        // The most recent alert that should have reached the overlay, from
        // the ledger. A refused token is the token wire's finding, not this
        // one's, so one cause never lights two wires.
        $latest = self::latestScoredAlert($user);
        $delivering = match (true) {
            $latest === null => WiringCatalog::NOT_APPLICABLE,
            in_array($latest->outcome, [DeliveryOutcome::Failed->value, DeliveryOutcome::RenderFailed->value], true) => WiringCatalog::MISSING,
            default => WiringCatalog::SATISFIED,
        };
        if ($latest !== null) {
            $when = Carbon::parse($latest->created_at)->diffForHumans();
            $context[] = match ($latest->outcome) {
                DeliveryOutcome::Delivered->value => 'Last alert reached '.$latest->connections.' '.($latest->connections === 1 ? 'connection' : 'connections').' '.$when,
                DeliveryOutcome::NoListener->value => 'Last alert was sent '.$when.' with no overlay open',
                DeliveryOutcome::TokenInvalid->value => 'Last alert failed '.$when.': Twitch refused the login',
                DeliveryOutcome::Failed->value => 'Last alert failed '.$when.' on the way to your overlay',
                default => 'Last alert could not be built '.$when,
            };
        } else {
            $context[] = 'No alert has fired in the last 7 days';
        }

        // Only a question while live: an overlay that is closed between
        // streams is not a loose end. While live, the last connection count
        // Reverb reported is the answer.
        $live = StreamState::forUser($user)->isConfidentlyLive();
        $lastDelivery = $live ? app(BroadcastMeter::class)->lastDeliveryFor((string) $user->twitch_id) : null;
        $listening = match (true) {
            ! $live, $lastDelivery === null => WiringCatalog::NOT_APPLICABLE,
            $lastDelivery['connections'] >= 1 => WiringCatalog::SATISFIED,
            default => WiringCatalog::MISSING,
        };
        if ($live && $lastDelivery !== null) {
            $context[] = 'Live now: the last update reached '.$lastDelivery['connections'].' '.($lastDelivery['connections'] === 1 ? 'connection' : 'connections');
        }

        return [
            'key' => 'account',
            'label' => 'Your alerts',
            'context' => $context,
            'states' => [
                'alerts.token_valid' => $tokenValid ? WiringCatalog::SATISFIED : WiringCatalog::MISSING,
                'alerts.subscribed' => $subscribed ? WiringCatalog::SATISFIED : WiringCatalog::MISSING,
                'alerts.delivering' => $delivering,
                'alerts.overlay_listening' => $listening,
            ],
        ];
    }

    /**
     * The newest scored ledger row across both event tables in the last seven
     * days, or null. Scored means the row had an alert that should have
     * reached the overlay; no_target rows are context, never evidence.
     */
    private static function latestScoredAlert(User $user): ?object
    {
        $scored = array_map(
            fn (DeliveryOutcome $o) => $o->value,
            array_filter(DeliveryOutcome::cases(), fn (DeliveryOutcome $o) => $o->isScored()),
        );
        $since = now()->subDays(7);

        $twitch = DB::table('twitch_events')
            ->where('user_id', $user->id)
            ->whereIn('outcome', $scored)
            ->where('created_at', '>=', $since)
            ->select('outcome', 'connections', 'created_at');

        $external = DB::table('external_events')
            ->where('user_id', $user->id)
            ->whereIn('outcome', $scored)
            ->where('created_at', '>=', $since)
            ->select('outcome', 'connections', 'created_at');

        return $twitch->unionAll($external)->orderByDesc('created_at')->limit(1)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private static function botSubject(User $user): array
    {
        $commandCount = BotCommand::where('user_id', $user->id)->where('enabled', true)->count()
            + ListAppender::where('user_id', $user->id)->where('enabled', true)->count()
            + ListMetaCommand::where('user_id', $user->id)->where('enabled', true)->count()
            + BotAlias::where('user_id', $user->id)->count();

        // No commands means the question does not arise. Telling someone with
        // an empty channel to add a bot is a suggestion, and this page does
        // not make suggestions.
        $state = match (true) {
            $commandCount === 0 => WiringCatalog::NOT_APPLICABLE,
            (bool) $user->bot_enabled => WiringCatalog::SATISFIED,
            default => WiringCatalog::MISSING,
        };

        $context = $commandCount > 0
            ? [$commandCount.' chat '.($commandCount === 1 ? 'command' : 'commands').' set up']
            : [];

        // Whether the bot is actually listening, from its own reports. Only a
        // question once the toggle is on (the toggle wire owns "off") and the
        // bot has reported in at all (a silent bot is a platform matter, not
        // this streamer's loose end). See BotPresence.
        $presence = app(BotPresence::class);
        $login = strtolower($user->twitch_data['login'] ?? '');
        $present = match (true) {
            $state !== WiringCatalog::SATISFIED, $login === '', ! $presence->reporting() => WiringCatalog::NOT_APPLICABLE,
            $presence->present($login) => WiringCatalog::SATISFIED,
            default => WiringCatalog::MISSING,
        };
        if ($state === WiringCatalog::SATISFIED && $login !== '') {
            $seenAt = $presence->seenAt($login);
            $context[] = match (true) {
                ! $presence->reporting() => 'The bot has not reported in yet',
                $present === WiringCatalog::SATISFIED => 'The bot last confirmed your chat '.Carbon::createFromTimestamp($seenAt)->diffForHumans(),
                $seenAt !== null => 'The bot last confirmed your chat '.Carbon::createFromTimestamp($seenAt)->diffForHumans(),
                default => 'The bot has never confirmed your chat',
            };
        }

        return [
            'key' => 'account',
            'label' => 'Your channel',
            'context' => $context,
            'states' => ['bot.in_chat' => $state, 'bot.present' => $present],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listSubjects(User $user): array
    {
        // An enabled !list meta-command reads every list the user owns - its
        // vocabulary (count, first, last, random, search, draw) takes a slug -
        // so it satisfies readability for all of them at once rather than
        // per list.
        $hasMetaCommand = ListMetaCommand::where('user_id', $user->id)
            ->where('enabled', true)
            ->exists();

        $boundary = self::READ_TAG_BOUNDARY;

        // Every list is injected into every overlay payload regardless of use,
        // so the payload cannot say which are actually read. The template
        // SOURCE can. Kept as EXISTS subqueries so template HTML is never
        // hydrated just to answer a boolean.
        $rows = DB::table('option_sets as o')
            ->where('o.user_id', $user->id)
            ->orderBy('o.slug')
            ->select('o.id', 'o.slug', 'o.label', 'o.recipe_instance_id')
            ->selectRaw("COALESCE((o.event_feed->>'enabled') = 'true', false) as feed_enabled")
            ->selectRaw('EXISTS (SELECT 1 FROM list_appenders a WHERE a.target_list_id = o.id AND a.user_id = o.user_id AND a.enabled) as has_appender')
            ->selectRaw('(SELECT a.command FROM list_appenders a WHERE a.target_list_id = o.id AND a.user_id = o.user_id AND a.enabled ORDER BY a.id LIMIT 1) as appender_command')
            ->selectRaw(
                "EXISTS (SELECT 1 FROM overlay_templates t WHERE t.owner_id = o.user_id AND (
                    COALESCE(t.html, '') ~ ('c:list:' || o.slug || '{$boundary}')
                 OR COALESCE(t.head, '') ~ ('c:list:' || o.slug || '{$boundary}')
                 OR COALESCE(t.css,  '') ~ ('c:list:' || o.slug || '{$boundary}')
                )) as read_by_overlay"
            )
            ->selectRaw(
                "EXISTS (SELECT 1 FROM bot_commands b WHERE b.user_id = o.user_id AND b.enabled
                 AND COALESCE(b.reply, '') ~ ('c:list:' || o.slug || '{$boundary}')) as read_by_command"
            )
            ->get();

        $subjects = [];

        foreach ($rows as $row) {
            $readable = $hasMetaCommand || $row->read_by_overlay || $row->read_by_command;

            // What fills it, stated as fact rather than as a checklist. A list
            // with none of these is hand-curated from the dashboard, which is
            // a legitimate setup and not a finding.
            $context = [];
            if ($row->has_appender) {
                $context[] = 'Chat adds to it with !'.$row->appender_command;
            }
            if ($row->feed_enabled) {
                $context[] = 'Filled by the recent-events feed';
            }
            if ($row->recipe_instance_id !== null) {
                $context[] = 'Installed by a recipe';
            }
            if ($context === []) {
                $context[] = 'You fill this one from the dashboard';
            }

            if ($readable) {
                $context[] = match (true) {
                    (bool) $row->read_by_overlay => 'An overlay shows it',
                    (bool) $row->read_by_command => 'A chat command shows it',
                    default => 'The !list command can show it',
                };
            }

            $subjects[] = [
                'key' => 'list:'.$row->slug,
                // Falls back to the slug rather than a dash: an unlabelled
                // list still has a name the owner will recognise.
                'label' => filled($row->label) ? $row->label : $row->slug,
                'context' => $context,
                'states' => [
                    'lists.readable' => $readable ? WiringCatalog::SATISFIED : WiringCatalog::MISSING,
                ],
            ];
        }

        return $subjects;
    }
}
