<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * TemplateDataMapperService
 *
 * Centralised service for all template tag mapping and data transformation.
 * This consolidates the mapping logic from JsonTemplateParserService to avoid duplication.
 *
 * - This service is the SINGLE SOURCE OF TRUTH for template tag mappings
 * - Both template generation AND template parsing use this same mapping logic
 * - This ensures consistency between database tags and runtime template parsing
 */
class TemplateDataMapperService
{
    private const array NUMERIC_TAGS = [
        'followers_total', 'followed_total', 'subscribers_total', 'subscribers_points',
        'user_view_count', 'goals_latest_target', 'goals_latest_current', 'channel_delay',
    ];

    private const array BOOLEAN_TAGS = [
        'channel_is_branded', 'subscribers_latest_is_gift',
    ];

    /**
     * Event payload list fields to flatten by index into event.{field}.{i}.{key}.
     * Twitch caps polls at 5 choices and predictions at 10 outcomes; hype train
     * top_contributions is unbounded but practically short, so cap at 3.
     */
    private const array INDEXED_LIST_FIELDS = [
        'top_contributions' => 3,
        'choices' => 5,
        'outcomes' => 10,
    ];

    /**
     * User-scope list fields that [[[foreach:alias as item]]] can iterate over.
     * Each entry maps a preference cap key to a source path in the Twitch payload
     * and the template-facing alias. Caps come from User::foreachCaps() — the
     * value here is only the fallback when no user caps are provided.
     */
    private const array INDEXED_USER_SCOPE_FIELDS = [
        'subscribers' => ['source' => 'subscribers.data', 'alias' => 'subscribers', 'default_cap' => 10],
        'goals' => ['source' => 'goals.data', 'alias' => 'goals', 'default_cap' => 3],
        'followers' => ['source' => 'channel_followers.data', 'alias' => 'channel_followers', 'default_cap' => 5],
        'followed' => ['source' => 'followed_channels.data', 'alias' => 'followed_channels', 'default_cap' => 5],
    ];

    /**
     * Public so callers (extractTemplateTags, settings defaults) can reuse the
     * same alias -> default-cap mapping without reaching into private state.
     *
     * @return array<string, array{alias: string, default_cap: int}>
     */
    public static function userScopeIterables(): array
    {
        return array_map(function ($spec) {
            return ['alias' => $spec['alias'], 'default_cap' => $spec['default_cap']];
        }, self::INDEXED_USER_SCOPE_FIELDS);
    }

    /**
     * Charity/hype-train payloads use {value, decimal_places, currency} money objects.
     * We emit event.{field}.formatted alongside the raw fields so templates aren't
     * stuck with minor-unit integers.
     */
    private const array MONEY_FIELDS = [
        'amount', 'target_amount', 'current_amount', 'total', 'last_contribution',
    ];

    /**
     * MASTER MAPPING - Single source of truth for all template tag mappings
     * Format: 'json_path' => 'template_tag_name'
     * This is used by BOTH JsonTemplateParserService AND template parsing
     */
    private function getTemplateMappings(): array
    {
        return [
            // User data mappings
            'user.id' => 'user_id',
            'user.login' => 'user_login',
            'user.display_name' => 'user_name',
            'user.type' => 'user_type',
            'user.broadcaster_type' => 'user_broadcaster_type',
            'user.description' => 'user_description',
            'user.profile_image_url' => 'user_avatar',
            'user.offline_image_url' => 'user_offline_banner',
            'user.view_count' => 'user_view_count',
            'user.email' => 'user_email',
            'user.created_at' => 'user_created',

            // Channel data mappings
            'channel.broadcaster_id' => 'channel_id',
            'channel.broadcaster_login' => 'channel_login',
            'channel.broadcaster_name' => 'channel_name',
            'channel.broadcaster_language' => 'channel_language',
            'channel.game_id' => 'channel_game_id',
            'channel.game_name' => 'channel_game',
            'channel.title' => 'channel_title',
            'channel.delay' => 'channel_delay',
            'channel.tags' => 'channel_tags_count',
            'channel.tags.0' => 'channel_tags_0',
            'channel.tags.1' => 'channel_tags_1',
            'channel.tags.2' => 'channel_tags_2',
            'channel.tags.3' => 'channel_tags_3',
            'channel.tags.4' => 'channel_tags_4',
            'channel.tags.5' => 'channel_tags_5',
            'channel.tags.6' => 'channel_tags_6',
            'channel.tags.7' => 'channel_tags_7',
            'channel.tags.8' => 'channel_tags_8',
            'channel.tags.9' => 'channel_tags_9',
            'channel.content_classification_labels' => 'channel_content_labels',
            'channel.is_branded_content' => 'channel_is_branded',

            // Channel Followers mappings
            'channel_followers.total' => 'followers_total',
            'channel_followers.data.0.user_id' => 'followers_latest_user_id',
            'channel_followers.data.0.user_login' => 'followers_latest_user_login',
            'channel_followers.data.0.user_name' => 'followers_latest_user_name',
            'channel_followers.data.0.followed_at' => 'followers_latest_date',

            // Followed channels mappings
            'followed_channels.total' => 'followed_total',
            'followed_channels.data.0.broadcaster_id' => 'followed_latest_id',
            'followed_channels.data.0.broadcaster_login' => 'followed_latest_login',
            'followed_channels.data.0.broadcaster_name' => 'followed_latest_name',
            'followed_channels.data.0.followed_at' => 'followed_latest_date',

            // Channel Subscribers mappings
            'subscribers.points' => 'subscribers_points',
            'subscribers.total' => 'subscribers_total',
            'subscribers.data.0.broadcaster_id' => 'subscribers_latest_broadcaster_id',
            'subscribers.data.0.broadcaster_login' => 'subscribers_latest_broadcaster_login',
            'subscribers.data.0.broadcaster_name' => 'subscribers_latest_broadcaster_name',
            'subscribers.data.0.gifter_id' => 'subscribers_latest_gifter_id',
            'subscribers.data.0.gifter_login' => 'subscribers_latest_gifter_login',
            'subscribers.data.0.gifter_name' => 'subscribers_latest_gifter_name',
            'subscribers.data.0.is_gift' => 'subscribers_latest_is_gift',
            'subscribers.data.0.plan_name' => 'subscribers_latest_plan_name',
            'subscribers.data.0.tier' => 'subscribers_latest_tier',
            'subscribers.data.0.user_id' => 'subscribers_latest_user_id',
            'subscribers.data.0.user_name' => 'subscribers_latest_user_name',
            'subscribers.data.0.user_login' => 'subscribers_latest_user_login',

            // Goals mappings
            'goals.total' => 'goals_data_count',
            'goals.data.0.type' => 'goals_latest_type',
            'goals.data.0.target' => 'goals_latest_target',
            'goals.data.0.current' => 'goals_latest_current',
            'goals.data.0.description' => 'goals_latest_description',
            'goals.data.0.created_at' => 'goals_latest_created_at',
        ];
    }

    /**
     * Get standardised template tag name from JSON path
     * This replaces JsonTemplateParserService::getStandardizedTagName()
     */
    public function getStandardizedTagName(string $jsonPath): string
    {
        $mappings = $this->getTemplateMappings();

        // First, try the exact match
        if (isset($mappings[$jsonPath])) {
            return $mappings[$jsonPath];
        }

        // If no exact match, build a logical name from a path
        $parts = explode('.', $jsonPath);

        // Handle array access like "data.0.field_name" -> prefix with parent + "latest_" + field
        if (count($parts) >= 3 && $parts[count($parts) - 2] === '0' && $parts[count($parts) - 3] === 'data') {
            $parentObject = $parts[count($parts) - 4] ?? 'unknown';
            $fieldName = $parts[count($parts) - 1];

            return $parentObject.'_latest_'.$fieldName;
        }

        // For simple paths, convert dots to underscores
        return str_replace('.', '_', $jsonPath);
    }

    /**
     * Transform Twitch API data structure into template-friendly flat structure
     * This uses the same mappings as getStandardizedTagName for consistency.
     *
     * @param  array|null  $caps  Per-user foreach caps (subscribers, goals,
     *                            followers, followed). When null, default caps
     *                            from INDEXED_USER_SCOPE_FIELDS are used.
     */
    public function mapTwitchDataForTemplates(array $twitchData, string $overlayName, ?array $caps = null): array
    {
        $mappings = $this->getTemplateMappings();
        $templateData = [
            'overlay_name' => $overlayName,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        // Apply all mappings with error handling
        foreach ($mappings as $jsonPath => $templateTag) {
            $value = $this->getNestedValue($twitchData, $jsonPath);

            if ($value !== null) {
                // Apply formatting based on a tag type
                $templateData[$templateTag] = $this->formatValueForTemplate($value, $templateTag);
            } else {
                // Provide default values for missing data
                $templateData[$templateTag] = $this->getDefaultValue($templateTag);
            }
        }

        // Emit indexed flat keys for user-scope iterables so [[[foreach:subscribers as s]]]
        // has something to resolve. Runs after the scalar mapping so [[[subscribers_latest_*]]]
        // still works alongside [[[foreach:subscribers as s]]][[[s.*]]][[[endforeach]]].
        return array_merge(
            $templateData,
            $this->buildUserScopeIndexedKeys($twitchData, $caps)
        );
    }

    /**
     * Build indexed flat keys for the 4 user-scope arrays (subscribers, goals,
     * followers, followed) based on per-user caps. Returns entries like:
     *   subscribers.count        => N
     *   subscribers.0.user_name  => "..."
     *   subscribers.1.user_name  => "..."
     *
     * `count` is the raw count from the Twitch payload, not capped. Items are
     * sliced to the cap. Existing "single latest" flat keys (subscribers_latest_*)
     * are emitted separately by the scalar mapping.
     */
    private function buildUserScopeIndexedKeys(array $twitchData, ?array $caps): array
    {
        $out = [];

        foreach (self::INDEXED_USER_SCOPE_FIELDS as $key => $spec) {
            $cap = (int) ($caps[$key] ?? $spec['default_cap']);
            $cap = max(1, $cap);

            $list = $this->getNestedValue($twitchData, $spec['source']);
            if (! is_array($list) || ! array_is_list($list)) {
                $out[$spec['alias'].'.count'] = 0;

                continue;
            }

            $out[$spec['alias'].'.count'] = count($list);

            foreach (array_slice($list, 0, $cap) as $i => $item) {
                if (! is_array($item) || array_is_list($item)) {
                    $out[$spec['alias'].'.'.$i] = $this->formatValueForTemplate($item, $spec['alias'].'.'.$i);

                    continue;
                }

                foreach ($item as $subKey => $subValue) {
                    $tag = $spec['alias'].'.'.$i.'.'.$subKey;
                    $out[$tag] = $this->formatValueForTemplate($subValue, $tag);
                }
            }
        }

        return $out;
    }

    /**
     * Get sample data for template previews and testing
     * This matches the structure of the real mapped data
     */
    public function getSampleTemplateData(): array
    {
        return [
            'overlay_name' => 'My Awesome Overlay',
            'timestamp' => now()->format('Y-m-d H:i:s'),

            // User information
            'user_id' => '123456789',
            'user_login' => 'wilko_dj',
            'user_name' => 'wilko_dj',
            'user_type' => '',
            'user_broadcaster_type' => 'partner',
            'user_description' => 'Welcome to my awesome stream!',
            'user_avatar' => 'https://static-cdn.jtvnw.net/jtv_user_pictures/7db44749-286f-4db0-9c99-574b16170d44-profile_image-300x300.png',
            'user_offline_banner' => '',
            'user_view_count' => '123,456',
            'user_email' => 'streamer@example.com',
            'user_created' => '2 years ago',

            // Channel information
            'channel_id' => '123456789',
            'channel_login' => 'wilko_dj',
            'channel_name' => 'wilko_dj',
            'channel_language' => 'en',
            'channel_game_id' => '509658',
            'channel_game' => 'Just Chatting',
            'channel_title' => 'Playing Awesome Game - Come Join!',
            'channel_delay' => '0',
            'channel_tags' => 'Gaming, Fun, Community',
            'channel_content_labels' => 'None',
            'channel_is_branded' => 'false',

            // Follower information
            'followers_total' => '1,234',
            'followers_latest_id' => '987654321',
            'followers_latest_login' => 'newfollower123',
            'followers_latest_name' => 'NewFollower123',
            'followers_latest_date' => '2 hours ago',

            // Followed channels information
            'followed_total' => '567',
            'followed_latest_id' => '111222333',
            'followed_latest_login' => 'coolstreamer',
            'followed_latest_name' => 'CoolStreamer',
            'followed_latest_date' => '1 day ago',

            // Subscriber information
            'subscribers_total' => '89',
            'subscribers_points' => '12,345',
            'subscribers_latest_user_id' => '444555666',
            'subscribers_latest_user_login' => 'newsubscriber',
            'subscribers_latest_user_name' => 'NewSubscriber',
            'subscribers_latest_broadcaster_id' => '123456789',
            'subscribers_latest_broadcaster_login' => 'streamername',
            'subscribers_latest_broadcaster_name' => 'StreamerName',
            'subscribers_latest_is_gift' => 'false',
            'subscribers_latest_tier' => '1000',
            'subscribers_latest_plan_name' => 'Tier 1',
            'subscribers_latest_gifter_id' => 'N/A',
            'subscribers_latest_gifter_login' => 'N/A',
            'subscribers_latest_gifter_name' => 'N/A',

            // Goal information
            'goals_latest_type' => 'follower',
            'goals_latest_target' => '2,000',
            'goals_latest_current' => '1,234',
            'goals_latest_description' => 'Road to 2K followers!',
            'goals_latest_created_at' => '1 week ago',
        ];
    }

    /**
     * Get a list of all available template tags with descriptions
     * This helps with documentation and validation
     */
    public function getAvailableTemplateTags(): array
    {
        return [
            // Overlay metadata
            'overlay_name' => 'Name of the overlay',
            'timestamp' => 'Current timestamp',

            // User information
            'user_id' => 'User ID',
            'user_login' => 'User login name',
            'user_name' => 'User display name',
            'user_type' => 'User type',
            'user_broadcaster_type' => 'Broadcaster type (partner, affiliate, etc.)',
            'user_description' => 'User bio/description',
            'user_avatar' => 'Profile image URL',
            'user_offline_banner' => 'Offline image URL',
            'user_view_count' => 'Total view count',
            'user_email' => 'User email',
            'user_created' => 'Account creation date',

            // Channel information
            'channel_id' => 'Channel ID',
            'channel_login' => 'Channel login name',
            'channel_name' => 'Channel display name',
            'channel_language' => 'Channel language',
            'channel_game_id' => 'Current game/category ID',
            'channel_game' => 'Current game/category name',
            'channel_title' => 'Stream title',
            'channel_delay' => 'Stream delay in seconds',
            'channel_tags' => 'Channel tags',
            'channel_content_labels' => 'Content classification labels',
            'channel_is_branded' => 'Whether channel has branded content',

            // Follower information
            'followers_total' => 'Total number of followers',
            'followers_latest_id' => 'Latest follower ID',
            'followers_latest_login' => 'Latest follower login',
            'followers_latest_name' => 'Latest follower name',
            'followers_latest_date' => 'Latest follow date',

            // Followed channels information
            'followed_total' => 'Total followed channels',
            'followed_latest_id' => 'Latest followed channel ID',
            'followed_latest_login' => 'Latest followed channel login',
            'followed_latest_name' => 'Latest followed channel name',
            'followed_latest_date' => 'Latest follow date',

            // Subscriber information
            'subscribers_total' => 'Total number of subscribers',
            'subscribers_points' => 'Subscriber points',
            'subscribers_latest_user_id' => 'Latest subscriber user ID',
            'subscribers_latest_user_login' => 'Latest subscriber login',
            'subscribers_latest_user_name' => 'Latest subscriber name',
            'subscribers_latest_broadcaster_id' => 'Broadcaster ID',
            'subscribers_latest_broadcaster_login' => 'Broadcaster login',
            'subscribers_latest_broadcaster_name' => 'Broadcaster name',
            'subscribers_latest_is_gift' => 'Whether subscription is a gift',
            'subscribers_latest_tier' => 'Subscription tier',
            'subscribers_latest_plan_name' => 'Subscription plan name',
            'subscribers_latest_gifter_id' => 'Gift giver ID (if applicable)',
            'subscribers_latest_gifter_login' => 'Gift giver login (if applicable)',
            'subscribers_latest_gifter_name' => 'Gift giver name (if applicable)',

            // Goal information
            'goals_latest_type' => 'Goal type',
            'goals_latest_target' => 'Goal target',
            'goals_latest_current' => 'Current progress',
            'goals_latest_description' => 'Goal description',
            'goals_latest_created_at' => 'Goal creation date',
        ];
    }

    /**
     * Get category mappings for organising template tags
     * This is used by JsonTemplateParserService for database organisation
     */
    public function getTagCategories(): array
    {
        return [
            'user' => [
                'display_name' => 'User Information',
                'description' => 'Basic user account information',
                'tags' => ['user_id', 'user_login', 'user_name', 'user_type', 'user_broadcaster_type', 'user_description', 'user_avatar', 'user_offline_banner', 'user_view_count', 'user_email', 'user_created'],
            ],
            'channel' => [
                'display_name' => 'Channel Information',
                'description' => 'Channel settings and current stream info',
                'tags' => ['channel_id', 'channel_login', 'channel_name', 'channel_language', 'channel_game_id', 'channel_game', 'channel_title', 'channel_delay', 'channel_tags', 'channel_content_labels', 'channel_is_branded'],
            ],
            'followers' => [
                'display_name' => 'Followers',
                'description' => 'Follower statistics and latest follower info',
                'tags' => ['followers_total', 'followers_latest_id', 'followers_latest_login', 'followers_latest_name', 'followers_latest_date'],
            ],
            'followed' => [
                'display_name' => 'Followed Channels',
                'description' => 'Channels that this user follows',
                'tags' => ['followed_total', 'followed_latest_id', 'followed_latest_login', 'followed_latest_name', 'followed_latest_date'],
            ],
            'subscribers' => [
                'display_name' => 'Subscribers',
                'description' => 'Subscriber statistics and latest subscriber info',
                'tags' => ['subscribers_total', 'subscribers_points', 'subscribers_latest_user_id', 'subscribers_latest_user_login', 'subscribers_latest_user_name', 'subscribers_latest_broadcaster_id', 'subscribers_latest_broadcaster_login', 'subscribers_latest_broadcaster_name', 'subscribers_latest_is_gift', 'subscribers_latest_tier', 'subscribers_latest_plan_name', 'subscribers_latest_gifter_id', 'subscribers_latest_gifter_login', 'subscribers_latest_gifter_name'],
            ],
            'goals' => [
                'display_name' => 'Goals',
                'description' => 'Channel goals and progress',
                'tags' => ['goals_latest_type', 'goals_latest_target', 'goals_latest_current', 'goals_latest_description', 'goals_latest_created_at'],
            ],
            'overlay' => [
                'display_name' => 'Overlay Metadata',
                'description' => 'Information about the overlay itself',
                'tags' => ['overlay_name', 'timestamp'],
            ],
            'event' => [
                'display_name' => 'Event Data',
                'description' => 'Dynamic event data from Twitch EventSub',
                'tags' => [
                    'event.type', 'event.user_id', 'event.user_login', 'event.user_name',
                    'event.broadcaster_user_id', 'event.broadcaster_user_login', 'event.broadcaster_user_name',
                    'event.tier', 'event.tier_display', 'event.is_gift', 'event.total', 'event.cumulative_total', 'event.is_anonymous',
                    'event.message', 'event.bits', 'event.viewers', 'event.from_broadcaster_user_id',
                    'event.from_broadcaster_user_login', 'event.from_broadcaster_user_name',
                    'event.to_broadcaster_user_id', 'event.to_broadcaster_user_login', 'event.to_broadcaster_user_name',
                    'event.moderator_user_id', 'event.moderator_user_login', 'event.moderator_user_name',
                    'event.reason', 'event.banned_at', 'event.ends_at', 'event.is_permanent',
                    'event.reward_id', 'event.reward.title', 'event.reward.prompt', 'event.reward.cost',
                    'event.user_input', 'event.status', 'event.redeemed_at',
                    // Hype train
                    'event.level', 'event.progress', 'event.goal', 'event.started_at', 'event.expires_at',
                    'event.ended_at', 'event.cooldown_ends_at',
                    'event.top_contributions.count',
                    'event.top_contributions.0.user_name', 'event.top_contributions.0.type', 'event.top_contributions.0.total',
                    'event.last_contribution.user_name', 'event.last_contribution.type', 'event.last_contribution.total',
                    // Charity
                    'event.charity_name', 'event.charity_description', 'event.charity_logo', 'event.charity_website',
                    'event.amount.value', 'event.amount.decimal_places', 'event.amount.currency', 'event.amount.formatted',
                    'event.current_amount.formatted', 'event.target_amount.formatted', 'event.stopped_at',
                    // Goals
                    'event.description', 'event.current_amount', 'event.target_amount', 'event.is_achieved',
                    // Polls
                    'event.title', 'event.choices.count',
                    'event.choices.total_votes', 'event.choices.total_channel_points_votes', 'event.choices.total_bits_votes',
                    'event.choices.0.title', 'event.choices.0.votes', 'event.choices.0.channel_points_votes', 'event.choices.0.bits_votes',
                    'event.bits_voting.is_enabled', 'event.bits_voting.amount_per_vote',
                    'event.channel_points_voting.is_enabled', 'event.channel_points_voting.amount_per_vote',
                    // Predictions
                    'event.winning_outcome_id', 'event.outcomes.count',
                    'event.outcomes.total_users', 'event.outcomes.total_channel_points',
                    'event.outcomes.0.title', 'event.outcomes.0.color', 'event.outcomes.0.users', 'event.outcomes.0.channel_points',
                    'event.locks_at',
                ],
            ],
        ];
    }

    /*
     *  Helper methods for template mapping to map and prune to a template’s tags in one call.
     */
    public function mapForTemplate(array $twitchData, string $overlayName, ?array $templateTags = null, ?array $eventData = null, ?array $caps = null): array
    {
        // Map the full Twitch dataset to your flat tag structure
        $all = $this->mapTwitchDataForTemplates($twitchData, $overlayName, $caps);

        // If event data is provided, add event.* tags
        if ($eventData) {
            $all = array_merge($all, $this->mapEventDataForTemplates($eventData));
        }

        // If a tag allowlist is provided, prune to only those keys
        if (is_array($templateTags) && count($templateTags)) {
            return array_intersect_key($all, array_flip($templateTags));
        }

        return $all;
    }

    /**
     * Helper: Get nested value from an array using dot notation
     * Enhanced with better error handling for missing data
     */
    private function getNestedValue(array $data, string $key)
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $nestedKey) {
            if (is_array($value) && isset($value[$nestedKey])) {
                $value = $value[$nestedKey];
            } else {
                // Log missing data for debugging but don't fail
                if ($nestedKey !== '0') { // Don't log missing array indices, those are expected
                    Log::debug("Missing nested value for key: $key at $nestedKey");
                }

                return null;
            }
        }

        return $value;
    }

    /**
     * Format value for template display
     */
    private function formatValueForTemplate(mixed $value, string $templateTag): mixed
    {
        if (is_array($value)) {
            if ($templateTag === 'channel_tags') {
                return implode(', ', $value);
            }

            return json_encode($value);
        }

        if (in_array($templateTag, self::BOOLEAN_TAGS, true)) {
            // Coerce anything truthy/falsy into real bool
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
        }

        if (in_array($templateTag, self::NUMERIC_TAGS, true) && is_numeric($value)) {
            return (! str_contains((string) $value, '.')) ? (int) $value : (float) $value;
        }

        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (str_contains($templateTag, '_date') || str_contains($templateTag, '_created')) {
            return $this->formatDate(is_string($value) ? $value : null);
        }

        return (string) $value;
    }

    /**
     * Get the default value for missing data
     */
    private function getDefaultValue(string $templateTag): string|int|false
    {
        if (in_array($templateTag, self::NUMERIC_TAGS, true)) {
            return 0;
        }
        if (in_array($templateTag, self::BOOLEAN_TAGS, true) ||
            str_contains($templateTag, 'is_') || str_contains($templateTag, '_gift')) {
            return false;
        }

        // Dates/strings default to ''
        return '';
    }

    /**
     * Format date for display
     */
    private function formatDate(?string $dateString): string
    {
        if (! $dateString) {
            return 'N/A';
        }

        try {
            return Carbon::parse($dateString)->diffForHumans();
        } catch (Exception) {
            return $dateString; // Return original if parsing fails
        }
    }

    /**
     * Format Twitch subscription tier for display
     * Converts "1000" -> "1", "2000" -> "2", "3000" -> "3", "Prime" -> "Prime"
     */
    private function formatTier($tier): string
    {
        if (! $tier) {
            return '';
        }

        $tierMap = [
            '1000' => '1',
            '2000' => '2',
            '3000' => '3',
            'Prime' => 'Prime',
        ];

        // Convert to string to handle both string and numeric inputs
        $tierStr = (string) $tier;

        return $tierMap[$tierStr] ?? $tierStr;
    }

    /**
     * Map EventSub event data to template tags
     */
    public function mapEventDataForTemplates(array $eventData): array
    {
        $mapped = [];

        // Add event type
        if (isset($eventData['subscription']['type'])) {
            $mapped['event.type'] = $eventData['subscription']['type'];
        }

        // Map all event fields with event. prefix
        if (isset($eventData['event'])) {
            foreach ($eventData['event'] as $key => $value) {
                $tagName = 'event.'.$key;

                // Special handling for tier field - provide both raw and formatted versions
                if ($key === 'tier') {
                    $mapped[$tagName] = $value; // Keep raw value for backward compatibility
                    $mapped['event.tier_display'] = $this->formatTier($value); // Add formatted version

                    continue;
                }

                // Indexed flattening for lists of objects (poll choices, prediction
                // outcomes, hype train top_contributions). Without this they end up
                // JSON-encoded by formatValueForTemplate, which templates can't use.
                if (isset(self::INDEXED_LIST_FIELDS[$key]) && is_array($value) && array_is_list($value)) {
                    $cap = self::INDEXED_LIST_FIELDS[$key];
                    $mapped[$tagName.'.count'] = count($value);

                    // Sum numeric fields across ALL items (before the cap) so
                    // templates can use true aggregates as denominators for
                    // progress bars / percentages. Polls get total_votes +
                    // total_channel_points_votes + total_bits_votes, predictions
                    // get total_users + total_channel_points, hype trains get
                    // total_total on top_contributions.
                    $sums = [];
                    foreach ($value as $item) {
                        if (! is_array($item) || array_is_list($item)) {
                            continue;
                        }
                        foreach ($item as $itemKey => $itemValue) {
                            if (is_numeric($itemValue)) {
                                $sums[$itemKey] = ($sums[$itemKey] ?? 0) + $itemValue;
                            }
                        }
                    }
                    foreach ($sums as $subkey => $sum) {
                        $mapped[$tagName.'.total_'.$subkey] = $sum + 0;
                    }

                    foreach (array_slice($value, 0, $cap) as $i => $item) {
                        if (is_array($item) && ! array_is_list($item)) {
                            foreach ($item as $itemKey => $itemValue) {
                                $nestedTag = $tagName.'.'.$i.'.'.$itemKey;
                                if (is_array($itemValue) && ! array_is_list($itemValue)) {
                                    // Money objects inside list items (e.g. outcome.channel_points)
                                    foreach ($itemValue as $mKey => $mValue) {
                                        $mapped[$nestedTag.'.'.$mKey] = $this->formatValueForTemplate($mValue, $nestedTag.'.'.$mKey);
                                    }
                                    if (in_array($itemKey, self::MONEY_FIELDS, true)) {
                                        $mapped[$nestedTag.'.formatted'] = $this->formatMoneyObject($itemValue);
                                    }
                                } else {
                                    $mapped[$nestedTag] = $this->formatValueForTemplate($itemValue, $nestedTag);
                                }
                            }
                        } else {
                            $mapped[$tagName.'.'.$i] = $this->formatValueForTemplate($item, $tagName.'.'.$i);
                        }
                    }

                    continue;
                }

                // Handle nested objects (flatten them)
                if (is_array($value) && ! array_is_list($value)) {
                    foreach ($value as $nestedKey => $nestedValue) {
                        $mapped['event.'.$key.'.'.$nestedKey] = $this->formatValueForTemplate($nestedValue, 'event.'.$key.'.'.$nestedKey);
                    }

                    // Derived formatted money string for charity amount objects.
                    if (in_array($key, self::MONEY_FIELDS, true)) {
                        $mapped[$tagName.'.formatted'] = $this->formatMoneyObject($value);
                    }

                    continue;
                }

                $mapped[$tagName] = $this->formatValueForTemplate($value, $tagName);
            }
        }

        return $mapped;
    }

    /**
     * Convert a Twitch money object {value, decimal_places, currency} into a
     * human-readable string. Twitch stores monetary amounts as integers in the
     * currency's minor units - templates would footgun without this.
     */
    private function formatMoneyObject(array $money): string
    {
        $value = $money['value'] ?? null;
        $decimals = $money['decimal_places'] ?? 2;
        $currency = $money['currency'] ?? '';

        if (! is_numeric($value)) {
            return '';
        }

        $amount = (float) $value / (10 ** (int) $decimals);
        $formatted = number_format($amount, (int) $decimals);
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥'];

        if (isset($symbols[$currency])) {
            return $symbols[$currency].$formatted;
        }

        return trim($currency.' '.$formatted);
    }

}
