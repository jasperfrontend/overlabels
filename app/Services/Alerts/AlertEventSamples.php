<?php

namespace App\Services\Alerts;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Services\External\ExternalServiceRegistry;
use App\Services\TemplateDataMapperService;

/**
 * A sample event for every event an alert can be triggered by, so an alert can
 * be shown without one happening: on a kit's page before anyone installs it.
 *
 * Every sample is a canned RAW payload, never a hand-written tag bag. An
 * external one runs through its service driver's normalizeEvent() and the
 * locale formatting a live event gets; a Twitch one runs through
 * TemplateDataMapperService::mapEventDataForTemplates(), the same mapper a
 * live EventSub notification goes through. So a sample cannot invent a tag a
 * real event does not produce, and a tag a driver adds shows up here on its
 * own. AlertEventSamplesTest pins that every event the Triggers page offers
 * has a sample, and that every Twitch sample produces every tag its reference
 * page documents.
 *
 * Several samples per event where an alert typically branches on it: a Ko-fi
 * first month vs a renewal, a Throne surprise gift, a crowdfunded gift.
 *
 * Invented people only, and images resolve to the Overlabels favicon (the
 * placeholder decided for previews in Aug 2026). Never a live value.
 */
final class AlertEventSamples
{
    public const string TWITCH = 'twitch';

    private const string WHEN = '2026-11-09T20:00:00Z';

    public function __construct(private readonly TemplateDataMapperService $mapper) {}

    /** Whether alerts can be triggered by this event at all. */
    public static function isKnown(string $service, string $eventType): bool
    {
        return $service === self::TWITCH
            ? isset(EventTemplateMapping::EVENT_TYPES[$eventType])
            : isset(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service][$eventType]);
    }

    /** The name the Triggers page uses for this event. */
    public static function label(string $service, string $eventType): string
    {
        return $service === self::TWITCH
            ? (EventTemplateMapping::EVENT_TYPES[$eventType] ?? $eventType)
            : (ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service][$eventType] ?? "{$service} {$eventType}");
    }

    /**
     * Every event an alert can be triggered by, grouped the way the Triggers
     * page groups them: Twitch first, then each service.
     *
     * @return list<array{service:string,events:list<array{event_type:string,label:string}>}>
     */
    public static function catalogue(): array
    {
        $groups = [['service' => self::TWITCH, 'events' => []]];
        foreach (EventTemplateMapping::EVENT_TYPES as $type => $label) {
            $groups[0]['events'][] = ['event_type' => $type, 'label' => $label];
        }
        foreach (ExternalEventTemplateMapping::SERVICE_EVENT_TYPES as $service => $types) {
            $events = [];
            foreach ($types as $type => $label) {
                $events[] = ['event_type' => $type, 'label' => $label];
            }
            $groups[] = ['service' => $service, 'events' => $events];
        }

        return $groups;
    }

    /**
     * The samples for one event, each with the `event.*` tags a live one
     * would hand the alert.
     *
     * @return list<array{key:string,label:string,tags:array<string,mixed>}>
     */
    public function for(string $service, string $eventType, string $locale = 'en-US'): array
    {
        if (! self::isKnown($service, $eventType)) {
            return [];
        }

        $label = self::label($service, $eventType);
        $out = [];

        if ($service === self::TWITCH) {
            foreach ($this->twitchPayloads($eventType) as $key => [$variant, $event]) {
                $out[] = [
                    'key' => $key,
                    'label' => $variant === null ? $label : "{$label} - {$variant}",
                    'tags' => $this->mapper->mapEventDataForTemplates([
                        'subscription' => ['type' => $eventType],
                        'event' => $event,
                    ]),
                ];
            }

            return $out;
        }

        $driver = ExternalServiceRegistry::driver($service);
        foreach ($this->externalPayloads($service, $eventType) as $key => [$variant, $payload]) {
            $out[] = [
                'key' => $key,
                'label' => $variant === null ? $label : "{$label} - {$variant}",
                'tags' => $driver->normalizeEvent($payload, $eventType)->withFormattedAmount($locale)->getTemplateTags(),
            ];
        }

        return $out;
    }

    private static function avatar(): string
    {
        return rtrim((string) config('app.url'), '/').'/favicon.svg';
    }

    /** @return array<string,string> */
    private static function user(string $name, string $id, string $prefix = 'user'): array
    {
        return [
            "{$prefix}_id" => $id,
            "{$prefix}_login" => strtolower($name),
            "{$prefix}_name" => $name,
            "{$prefix}_avatar" => self::avatar(),
        ];
    }

    /** @return array<string,string> */
    private static function broadcaster(): array
    {
        return self::user('YourChannel', '100000001', 'broadcaster_user');
    }

    /**
     * @return array<string, array{0:?string,1:array<string,mixed>}>
     */
    private function twitchPayloads(string $eventType): array
    {
        $b = self::broadcaster();
        $money = fn (int $value) => ['value' => $value, 'decimal_places' => 2, 'currency' => 'USD'];
        $charity = [
            'charity_name' => 'Example Ocean Trust',
            'charity_description' => 'Keeping coastlines clean.',
            'charity_logo' => self::avatar(),
            'charity_website' => 'https://example.org',
        ];
        $choices = [
            ['id' => 'c1', 'title' => 'Speedrun it', 'votes' => 64, 'channel_points_votes' => 20, 'bits_votes' => 0],
            ['id' => 'c2', 'title' => 'Take it slow', 'votes' => 31, 'channel_points_votes' => 5, 'bits_votes' => 0],
        ];
        $poll = [
            'id' => 'poll-1', 'title' => 'How do we play the next level?', 'choices' => $choices,
            'bits_voting' => ['is_enabled' => false, 'amount_per_vote' => 0],
            'channel_points_voting' => ['is_enabled' => true, 'amount_per_vote' => 100],
            'winners' => [$choices[0]],
            'started_at' => self::WHEN,
        ];
        $outcomes = [
            ['id' => 'o1', 'title' => 'We win', 'color' => 'blue', 'users' => 42, 'channel_points' => 18500],
            ['id' => 'o2', 'title' => 'We lose', 'color' => 'pink', 'users' => 17, 'channel_points' => 6200],
        ];
        $prediction = ['id' => 'pred-1', 'title' => 'Do we beat the boss first try?', 'outcomes' => $outcomes, 'started_at' => self::WHEN];
        $contribution = fn (string $name, string $id, int $total) => [...self::user($name, $id), 'type' => 'bits', 'total' => $total];
        $hype = [
            'id' => 'hype-1', 'level' => 3, 'total' => 3500, 'progress' => 900, 'goal' => 1800,
            'top_contributions' => [
                $contribution('CozyCartographer', '200000001', 1500),
                $contribution('NightOwlNora', '200000002', 1100),
                $contribution('PixelPaloma', '200000003', 900),
            ],
            'last_contribution' => $contribution('NightOwlNora', '200000002', 300),
            'started_at' => self::WHEN,
            'expires_at' => '2026-11-09T20:05:00Z',
        ];
        $goal = ['id' => 'goal-1', 'type' => 'follower', 'description' => 'Road to 1000', 'current_amount' => 912, 'target_amount' => 1000, 'started_at' => self::WHEN];
        $campaign = [...$charity, 'id' => 'charity-1', 'current_amount' => $money(41250), 'target_amount' => $money(100000)];

        return match ($eventType) {
            'channel.follow' => ['follow' => [null, [...$b, ...self::user('CozyCartographer', '200000001'), 'followed_at' => self::WHEN]]],
            'channel.subscribe' => [
                'tier1' => ['Tier 1', [...$b, ...self::user('NightOwlNora', '200000002'), 'tier' => '1000', 'is_gift' => false]],
                'tier3' => ['Tier 3', [...$b, ...self::user('PixelPaloma', '200000003'), 'tier' => '3000', 'is_gift' => false]],
            ],
            'channel.subscription.gift' => [
                'five' => ['5 gifts', [...$b, ...self::user('CozyCartographer', '200000001'), 'total' => 5, 'tier' => '1000', 'cumulative_total' => 23, 'is_anonymous' => false]],
                'anonymous' => ['anonymous', [...$b, 'user_id' => null, 'user_login' => null, 'user_name' => null, 'total' => 1, 'tier' => '1000', 'cumulative_total' => null, 'is_anonymous' => true]],
            ],
            'channel.subscription.message' => ['resub' => [null, [...$b, ...self::user('NightOwlNora', '200000002'), 'tier' => '1000',
                'message' => ['text' => 'Two years already! Best community on Twitch.', 'emotes' => []],
                'cumulative_months' => 24, 'streak_months' => 12, 'duration_months' => 1]]],
            'channel.cheer' => [
                'small' => ['100 bits', [...$b, ...self::user('PixelPaloma', '200000003'), 'is_anonymous' => false, 'message' => 'Cheer100 let us go!', 'bits' => 100]],
                'big' => ['1000 bits', [...$b, ...self::user('CozyCartographer', '200000001'), 'is_anonymous' => false, 'message' => 'Cheer1000 for the clutch play', 'bits' => 1000]],
            ],
            'channel.raid' => ['raid' => [null, [
                ...self::user('NightOwlNora', '200000002', 'from_broadcaster_user'),
                ...self::user('YourChannel', '100000001', 'to_broadcaster_user'),
                'viewers' => 128,
            ]]],
            'channel.channel_points_custom_reward_redemption.add' => ['redeem' => [null, [...$b, ...self::user('PixelPaloma', '200000003'),
                'id' => 'redemption-1', 'user_input' => 'Play the victory song!', 'status' => 'unfulfilled',
                'reward' => ['id' => 'reward-1', 'title' => 'Song request', 'cost' => 500, 'prompt' => 'Pick the next song'],
                'redeemed_at' => self::WHEN]]],
            'stream.online' => ['online' => [null, [...$b, 'id' => 'stream-1', 'type' => 'live', 'started_at' => self::WHEN]]],
            'stream.offline' => ['offline' => [null, $b]],
            'channel.update' => ['update' => [null, [...$b, 'title' => 'Chill speedruns and good company', 'language' => 'en',
                'category_id' => '509658', 'category_name' => 'Just Chatting', 'content_classification_labels' => []]]],
            'channel.hype_train.begin', 'channel.hype_train.progress' => ['hype' => [null, [...$b, ...$hype]]],
            'channel.hype_train.end' => ['end' => [null, [...$b, ...array_diff_key($hype, ['last_contribution' => 1, 'progress' => 1, 'goal' => 1, 'expires_at' => 1]),
                'ended_at' => '2026-11-09T20:05:00Z', 'cooldown_ends_at' => '2026-11-09T21:05:00Z']]],
            'channel.charity_campaign.donate' => ['donate' => [null, [...$b, ...self::user('CozyCartographer', '200000001'), ...$charity,
                'id' => 'charity-donation-1', 'campaign_id' => 'charity-1', 'amount' => $money(2500)]]],
            'channel.charity_campaign.start' => ['start' => [null, [...$b, ...$campaign, 'started_at' => self::WHEN]]],
            'channel.charity_campaign.progress' => ['progress' => [null, [...$b, ...$campaign]]],
            'channel.charity_campaign.stop' => ['stop' => [null, [...$b, ...$campaign, 'stopped_at' => self::WHEN]]],
            'channel.goal.begin', 'channel.goal.progress' => ['goal' => [null, [...$b, ...$goal]]],
            'channel.goal.end' => ['end' => [null, [...$b, ...$goal, 'current_amount' => 1000, 'is_achieved' => true, 'ended_at' => self::WHEN]]],
            'channel.poll.begin' => ['begin' => [null, [...$b, ...$poll, 'ends_at' => '2026-11-09T20:02:00Z']]],
            'channel.poll.progress' => ['progress' => [null, [...$b, ...$poll, 'ends_at' => '2026-11-09T20:02:00Z']]],
            'channel.poll.end' => ['end' => [null, [...$b, ...$poll, 'status' => 'completed', 'ended_at' => '2026-11-09T20:02:00Z']]],
            'channel.prediction.begin' => ['begin' => [null, [...$b, ...$prediction, 'locks_at' => '2026-11-09T20:03:00Z']]],
            'channel.prediction.progress' => ['progress' => [null, [...$b, ...$prediction, 'locks_at' => '2026-11-09T20:03:00Z']]],
            'channel.prediction.lock' => ['lock' => [null, [...$b, ...$prediction, 'locked_at' => '2026-11-09T20:03:00Z']]],
            'channel.prediction.end' => ['end' => [null, [...$b, ...$prediction, 'winning_outcome_id' => 'o1', 'status' => 'resolved', 'ended_at' => '2026-11-09T20:10:00Z']]],
            default => [],
        };
    }

    /**
     * Raw payloads in each service's own wire format, as its webhook (or the
     * bot, for checkin and tower) would deliver them.
     *
     * @return array<string, array{0:?string,1:array<string,mixed>}>
     */
    private function externalPayloads(string $service, string $eventType): array
    {
        $message = 'Keep going, this run has been unreal. Snacks are on me tonight!';

        return match ("{$service}:{$eventType}") {
            'kofi:donation' => ['tip' => [null, $this->kofi('Donation', ['from_name' => 'CozyCartographer', 'message' => $message, 'amount' => '5.00'])]],
            'kofi:subscription' => [
                'first' => ['first month', $this->kofi('Subscription', ['from_name' => 'NightOwlNora', 'amount' => '3.00', 'is_subscription_payment' => true, 'is_first_subscription_payment' => true, 'tier_name' => 'Snack Club'])],
                'renewal' => ['renewal', $this->kofi('Subscription', ['from_name' => 'NightOwlNora', 'message' => 'Another month, happily.', 'amount' => '3.00', 'is_subscription_payment' => true, 'tier_name' => 'Snack Club'])],
            ],
            'kofi:shop_order' => ['order' => [null, $this->kofi('Shop Order', ['from_name' => 'PixelPaloma', 'amount' => '24.99'])]],
            'kofi:commission' => ['commission' => [null, $this->kofi('Commission', ['from_name' => 'CozyCartographer', 'message' => 'Can you draw my cat as a knight?', 'amount' => '60.00'])]],

            'streamlabs:donation' => ['tip' => [null, ['type' => 'donation', 'message' => [[
                'id' => 'sample-1', '_id' => 'sample-1', 'name' => 'PixelPaloma', 'from' => 'PixelPaloma',
                'message' => $message, 'amount' => 13.37, 'currency' => 'USD',
            ]]]]],

            'fourthwall:donation' => ['tip' => [null, ['type' => 'DONATION', 'data' => [
                'id' => 'sample-1', 'username' => 'NightOwlNora', 'message' => $message, 'status' => 'COMPLETED',
                'amounts' => ['total' => ['value' => 10.0, 'currency' => 'GBP']],
            ]]]],

            'bmac:donation' => ['coffees' => ['3 coffees', $this->bmac('donation.created', ['amount' => 15.0, 'coffee_count' => 3, 'support_note' => $message])]],
            'bmac:recurring' => ['monthly' => [null, $this->bmac('recurring_donation.started', ['amount' => 5.0])]],
            'bmac:membership' => ['member' => [null, $this->bmac('membership.started', ['amount' => 8.0, 'support_note' => 'Happy to be here!'])]],
            'bmac:extra' => ['extra' => [null, $this->bmac('extra_purchase.created', ['amount' => 12.0, 'extras' => [['title' => 'Stream sticker pack']]])]],
            'bmac:wishlist' => ['wishlist' => [null, $this->bmac('wishlist_payment.created', ['amount' => 40.0, 'wishlist' => ['title' => 'New microphone arm']])]],
            'bmac:commission' => ['commission' => [null, $this->bmac('commission_order.created', ['amount' => 60.0, 'commission' => ['name' => 'Custom emote']])]],

            'throne:donation' => [
                'gift' => ['gift', $this->throne('gift_purchased', ['gifter_username' => 'PixelPaloma', 'message' => $message, 'price' => 4999, 'item_name' => 'Cold brew coffee maker'])],
                'surprise' => ['surprise gift', $this->throne('gift_purchased', ['gifter_username' => 'CozyCartographer', 'price' => 2999, 'item_name' => 'Mystery item', 'is_surprise_gift' => true])],
                'crowdfund' => ['crowdfunded', $this->throne('gift_crowdfunded', ['price' => 19999, 'item_name' => 'Streaming chair'])],
            ],

            'checkin:checkin' => ['checkin' => [null, ['type' => 'checkin', 'chatter_id' => '200000002', 'chatter_display_name' => 'NightOwlNora',
                'chatter_login' => 'nightowlnora', 'place_label' => 'Lisbon, Portugal', 'country_name' => 'Portugal', 'country_code' => 'PT',
                'lat' => 38.72, 'lng' => -9.14, 'distance' => 1843.2, 'at' => 1794182400]]],

            'tower:stack' => ['stack' => [null, ['type' => 'stack', 'chatter_id' => '200000003', 'chatter_display_name' => 'PixelPaloma',
                'chatter_login' => 'pixelpaloma', 'chatter_color' => '#B794FF', 'aim' => 'left', 'height' => 17, 'lean' => 1.4, 'room' => 2.6, 'at' => 1794182400]]],
            'tower:topple' => ['topple' => [null, ['type' => 'topple', 'chatter_id' => '200000001', 'chatter_display_name' => 'CozyCartographer',
                'chatter_login' => 'cozycartographer', 'chatter_color' => '#FF5E5B', 'aim' => 'right', 'height' => 23, 'lean' => 3.1, 'room' => 0, 'at' => 1794182400]]],

            'gps:location_update' => ['update' => [null, ['latitude' => 52.37, 'longitude' => 4.9, 'speed' => 4.2, 'altitude' => 2,
                'accuracy' => 5, 'bearing' => 90, 'battery' => 78, 'charging' => false, 'session_id' => 'sample-session', 'timestamp' => 1794182400]]],
            'gps:session_start' => ['start' => [null, ['event' => 'session_start', 'session_id' => 'sample-session', 'timestamp' => 1794182400]]],
            'gps:session_end' => ['end' => [null, ['event' => 'session_end', 'session_id' => 'sample-session', 'timestamp' => 1794186000]]],
            'gps:settings_sync' => ['sync' => [null, ['event' => 'settings_sync', 'session_id' => 'sample-session', 'timestamp' => 1794182400]]],

            default => [],
        };
    }

    /** @return array<string,mixed> */
    private function kofi(string $type, array $fields): array
    {
        return [
            'kofi_transaction_id' => 'sample-'.strtolower(str_replace(' ', '-', $type)),
            'type' => $type,
            'currency' => 'EUR',
            'message' => null,
            'is_public' => true,
            'is_subscription_payment' => false,
            'is_first_subscription_payment' => false,
            ...$fields,
        ];
    }

    /** @return array<string,mixed> */
    private function bmac(string $type, array $data): array
    {
        return ['type' => $type, 'live_mode' => true, 'data' => [
            'transaction_id' => 'sample-'.$type,
            'supporter_name' => 'CozyCartographer',
            'currency' => 'EUR',
            'note_hidden' => false,
            ...$data,
        ]];
    }

    /** @return array<string,mixed> */
    private function throne(string $type, array $data): array
    {
        return ['event_type' => $type, 'event_id' => 'sample-'.$type, 'data' => [
            'currency' => 'USD',
            'item_thumbnail_url' => self::avatar(),
            'is_surprise_gift' => false,
            ...$data,
        ]];
    }
}
