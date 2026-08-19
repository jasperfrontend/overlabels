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
        'winners' => 5,
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
     * event.* tags the mapper emits at render time from an EventSub payload.
     * They have no catalogue entry because they come from the event, not from
     * the Twitch data snapshot - so they are declared here and surfaced through
     * getTagCategories() only.
     */
    private const array EVENT_TAGS = [
        'event.type', 'event.user_id', 'event.user_login', 'event.user_name', 'event.user_avatar',
        'event.broadcaster_user_id', 'event.broadcaster_user_login', 'event.broadcaster_user_name', 'event.broadcaster_user_avatar',
        'event.tier', 'event.tier_display', 'event.is_gift', 'event.total', 'event.cumulative_total', 'event.is_anonymous',
        'event.message', 'event.bits', 'event.viewers', 'event.from_broadcaster_user_id',
        'event.from_broadcaster_user_login', 'event.from_broadcaster_user_name', 'event.from_broadcaster_user_avatar',
        'event.to_broadcaster_user_id', 'event.to_broadcaster_user_login', 'event.to_broadcaster_user_name', 'event.to_broadcaster_user_avatar',
        'event.moderator_user_id', 'event.moderator_user_login', 'event.moderator_user_name', 'event.moderator_user_avatar',
        'event.reason', 'event.banned_at', 'event.ends_at', 'event.is_permanent',
        'event.reward_id', 'event.reward.title', 'event.reward.prompt', 'event.reward.cost',
        'event.user_input', 'event.status', 'event.redeemed_at',
        // Hype train
        'event.level', 'event.progress', 'event.goal', 'event.started_at', 'event.expires_at',
        'event.ended_at', 'event.cooldown_ends_at',
        'event.top_contributions.count',
        'event.top_contributions.0.user_name', 'event.top_contributions.0.user_avatar', 'event.top_contributions.0.type', 'event.top_contributions.0.total',
        'event.last_contribution.user_name', 'event.last_contribution.user_avatar', 'event.last_contribution.type', 'event.last_contribution.total',
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
    ];

    /**
     * THE TAG CATALOGUE - the one and only declaration of a static template tag.
     *
     * getTemplateMappings(), getTagCategories(), getAvailableTemplateTags() and
     * getSampleTemplateData() are all derived from this array, and
     * JsonTemplateParserService seeds the database from it. Adding a tag is one
     * entry here and nothing else.
     *
     * Before Aug 2026 these were four hand-maintained lists that had drifted
     * apart: the tag browser advertised `followers_latest_name` and
     * `channel_tags`, neither of which the mapping could ever produce, while the
     * names that did work carried auto-generated descriptions. Deriving all four
     * from one array is what makes that class of drift unrepresentable.
     *
     * Per entry:
     *   path     - dot path into the payload getExtendedUserData() returns.
     *   category - key into self::TAG_CATEGORY_META.
     *   type     - data_type persisted on the tag row.
     *   label    - display_name on the tag row.
     *   desc     - description shown in the tag browser.
     *   sample   - fallback sample, used for template previews and whenever the
     *              account has no live value for the path.
     */
    private const array TAG_CATALOG = [
        // ---- User (the most recent user who triggered an event; see TemplateTagsList.vue) ----
        'user_id' => ['path' => 'user.id', 'category' => 'user', 'type' => 'string', 'label' => 'ID', 'desc' => 'User ID', 'sample' => '123456789'],
        'user_login' => ['path' => 'user.login', 'category' => 'user', 'type' => 'string', 'label' => 'Login', 'desc' => 'User login name', 'sample' => 'wilko_dj'],
        'user_name' => ['path' => 'user.display_name', 'category' => 'user', 'type' => 'string', 'label' => 'Name', 'desc' => 'User display name', 'sample' => 'wilko_dj'],
        'user_type' => ['path' => 'user.type', 'category' => 'user', 'type' => 'string', 'label' => 'Type', 'desc' => 'User type', 'sample' => ''],
        'user_broadcaster_type' => ['path' => 'user.broadcaster_type', 'category' => 'user', 'type' => 'string', 'label' => 'Broadcaster Type', 'desc' => 'Broadcaster type (partner, affiliate, etc.)', 'sample' => 'partner'],
        'user_description' => ['path' => 'user.description', 'category' => 'user', 'type' => 'string', 'label' => 'Description', 'desc' => 'User bio/description', 'sample' => 'Welcome to my awesome stream!'],
        'user_avatar' => ['path' => 'user.profile_image_url', 'category' => 'user', 'type' => 'url', 'label' => 'Avatar', 'desc' => 'Profile image URL', 'sample' => 'https://static-cdn.jtvnw.net/jtv_user_pictures/7db44749-286f-4db0-9c99-574b16170d44-profile_image-300x300.png'],
        'user_offline_banner' => ['path' => 'user.offline_image_url', 'category' => 'user', 'type' => 'url', 'label' => 'Offline Banner', 'desc' => 'Offline image URL', 'sample' => ''],
        'user_view_count' => ['path' => 'user.view_count', 'category' => 'user', 'type' => 'integer', 'label' => 'View Count', 'desc' => 'Total view count (Twitch deprecated this field; it reports 0 for every account)', 'sample' => 123456],
        'user_email' => ['path' => 'user.email', 'category' => 'user', 'type' => 'string', 'label' => 'Email', 'desc' => 'Disabled - always returns a placeholder string', 'sample' => 'disabled@for-your-security'],
        'user_created' => ['path' => 'user.created_at', 'category' => 'user', 'type' => 'datetime', 'label' => 'Created', 'desc' => 'Account creation date', 'sample' => 1714435200],

        // ---- Channel (always your own channel, never an event actor) ----
        'channel_avatar' => ['path' => 'channel.avatar', 'category' => 'channel', 'type' => 'url', 'label' => 'Avatar', 'desc' => 'Your own profile image. Unlike user_avatar this never changes when someone triggers an event', 'sample' => 'https://static-cdn.jtvnw.net/jtv_user_pictures/7db44749-286f-4db0-9c99-574b16170d44-profile_image-300x300.png'],
        'channel_id' => ['path' => 'channel.broadcaster_id', 'category' => 'channel', 'type' => 'string', 'label' => 'ID', 'desc' => 'Channel ID', 'sample' => '123456789'],
        'channel_login' => ['path' => 'channel.broadcaster_login', 'category' => 'channel', 'type' => 'string', 'label' => 'Login', 'desc' => 'Channel login name', 'sample' => 'wilko_dj'],
        'channel_name' => ['path' => 'channel.broadcaster_name', 'category' => 'channel', 'type' => 'string', 'label' => 'Name', 'desc' => 'Channel display name', 'sample' => 'wilko_dj'],
        'channel_language' => ['path' => 'channel.broadcaster_language', 'category' => 'channel', 'type' => 'string', 'label' => 'Language', 'desc' => 'Channel language', 'sample' => 'en'],
        'channel_game_id' => ['path' => 'channel.game_id', 'category' => 'channel', 'type' => 'string', 'label' => 'Game ID', 'desc' => 'Current game/category ID', 'sample' => '509658'],
        'channel_game' => ['path' => 'channel.game_name', 'category' => 'channel', 'type' => 'string', 'label' => 'Game', 'desc' => 'Current game/category name', 'sample' => 'Just Chatting'],
        'channel_title' => ['path' => 'channel.title', 'category' => 'channel', 'type' => 'string', 'label' => 'Title', 'desc' => 'Stream title', 'sample' => 'Playing Awesome Game - Come Join!'],
        'channel_delay' => ['path' => 'channel.delay', 'category' => 'channel', 'type' => 'integer', 'label' => 'Delay', 'desc' => 'Stream delay in seconds', 'sample' => 0],
        'channel_tags' => ['path' => 'channel.tags', 'category' => 'channel', 'type' => 'string', 'label' => 'Tags', 'desc' => 'All channel tags, comma separated', 'sample' => 'Gaming, Fun, Community'],
        'channel_tags_0' => ['path' => 'channel.tags.0', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 1', 'desc' => 'Channel tag 1', 'sample' => 'Gaming'],
        'channel_tags_1' => ['path' => 'channel.tags.1', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 2', 'desc' => 'Channel tag 2', 'sample' => 'Fun'],
        'channel_tags_2' => ['path' => 'channel.tags.2', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 3', 'desc' => 'Channel tag 3', 'sample' => 'Community'],
        'channel_tags_3' => ['path' => 'channel.tags.3', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 4', 'desc' => 'Channel tag 4', 'sample' => ''],
        'channel_tags_4' => ['path' => 'channel.tags.4', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 5', 'desc' => 'Channel tag 5', 'sample' => ''],
        'channel_tags_5' => ['path' => 'channel.tags.5', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 6', 'desc' => 'Channel tag 6', 'sample' => ''],
        'channel_tags_6' => ['path' => 'channel.tags.6', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 7', 'desc' => 'Channel tag 7', 'sample' => ''],
        'channel_tags_7' => ['path' => 'channel.tags.7', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 8', 'desc' => 'Channel tag 8', 'sample' => ''],
        'channel_tags_8' => ['path' => 'channel.tags.8', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 9', 'desc' => 'Channel tag 9', 'sample' => ''],
        'channel_tags_9' => ['path' => 'channel.tags.9', 'category' => 'channel', 'type' => 'string', 'label' => 'Tag 10', 'desc' => 'Channel tag 10', 'sample' => ''],
        'channel_content_labels' => ['path' => 'channel.content_classification_labels', 'category' => 'channel', 'type' => 'string', 'label' => 'Content Labels', 'desc' => 'Content classification labels', 'sample' => ''],
        'channel_is_branded' => ['path' => 'channel.is_branded_content', 'category' => 'channel', 'type' => 'boolean', 'label' => 'Is Branded', 'desc' => 'Whether the stream is flagged as branded content', 'sample' => false],

        // ---- Followers ----
        'followers_total' => ['path' => 'channel_followers.total', 'category' => 'followers', 'type' => 'integer', 'label' => 'Total', 'desc' => 'Total number of followers', 'sample' => 1234],
        'followers_latest_user_id' => ['path' => 'channel_followers.data.0.user_id', 'category' => 'followers', 'type' => 'string', 'label' => 'Latest Follower ID', 'desc' => 'Latest follower ID', 'sample' => '987654321'],
        'followers_latest_user_login' => ['path' => 'channel_followers.data.0.user_login', 'category' => 'followers', 'type' => 'string', 'label' => 'Latest Follower Login', 'desc' => 'Latest follower login', 'sample' => 'newfollower123'],
        'followers_latest_user_name' => ['path' => 'channel_followers.data.0.user_name', 'category' => 'followers', 'type' => 'string', 'label' => 'Latest Follower Name', 'desc' => 'Latest follower display name', 'sample' => 'NewFollower123'],
        'followers_latest_date' => ['path' => 'channel_followers.data.0.followed_at', 'category' => 'followers', 'type' => 'datetime', 'label' => 'Latest Follow Date', 'desc' => 'Latest follow date', 'sample' => 1777672800],

        // ---- Followed channels ----
        'followed_total' => ['path' => 'followed_channels.total', 'category' => 'followed', 'type' => 'integer', 'label' => 'Total', 'desc' => 'Total followed channels', 'sample' => 567],
        'followed_latest_id' => ['path' => 'followed_channels.data.0.broadcaster_id', 'category' => 'followed', 'type' => 'string', 'label' => 'Latest ID', 'desc' => 'Latest followed channel ID', 'sample' => '111222333'],
        'followed_latest_login' => ['path' => 'followed_channels.data.0.broadcaster_login', 'category' => 'followed', 'type' => 'string', 'label' => 'Latest Login', 'desc' => 'Latest followed channel login', 'sample' => 'coolstreamer'],
        'followed_latest_name' => ['path' => 'followed_channels.data.0.broadcaster_name', 'category' => 'followed', 'type' => 'string', 'label' => 'Latest Name', 'desc' => 'Latest followed channel name', 'sample' => 'CoolStreamer'],
        'followed_latest_date' => ['path' => 'followed_channels.data.0.followed_at', 'category' => 'followed', 'type' => 'datetime', 'label' => 'Latest Follow Date', 'desc' => 'Latest follow date', 'sample' => 1777593600],

        // ---- Subscribers (Twitch serves these only to affiliates and partners,
        //      so they resolve to 0 / empty for everyone else - which renders as
        //      nothing, and is why they are offered to every account) ----
        'subscribers_total' => ['path' => 'subscribers.total', 'category' => 'subscribers', 'type' => 'integer', 'label' => 'Total', 'desc' => 'Total number of subscribers', 'sample' => 89],
        'subscribers_points' => ['path' => 'subscribers.points', 'category' => 'subscribers', 'type' => 'integer', 'label' => 'Points', 'desc' => 'Total subscriber points', 'sample' => 12345],
        'subscribers_latest_user_id' => ['path' => 'subscribers.data.0.user_id', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Subscriber ID', 'desc' => 'Latest subscriber ID', 'sample' => '444555666'],
        'subscribers_latest_user_login' => ['path' => 'subscribers.data.0.user_login', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Subscriber Login', 'desc' => 'Latest subscriber login', 'sample' => 'newsubscriber'],
        'subscribers_latest_user_name' => ['path' => 'subscribers.data.0.user_name', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Subscriber Name', 'desc' => 'Latest subscriber display name', 'sample' => 'NewSubscriber'],
        'subscribers_latest_tier' => ['path' => 'subscribers.data.0.tier', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Tier', 'desc' => 'Latest subscription tier (1000, 2000, 3000 or Prime)', 'sample' => '1000'],
        'subscribers_latest_plan_name' => ['path' => 'subscribers.data.0.plan_name', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Plan Name', 'desc' => 'Latest subscription plan name', 'sample' => 'Tier 1'],
        'subscribers_latest_is_gift' => ['path' => 'subscribers.data.0.is_gift', 'category' => 'subscribers', 'type' => 'boolean', 'label' => 'Latest Is Gift', 'desc' => 'Whether the latest subscription was gifted', 'sample' => false],
        'subscribers_latest_gifter_id' => ['path' => 'subscribers.data.0.gifter_id', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Gifter ID', 'desc' => 'Latest gifter ID', 'sample' => ''],
        'subscribers_latest_gifter_login' => ['path' => 'subscribers.data.0.gifter_login', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Gifter Login', 'desc' => 'Latest gifter login', 'sample' => ''],
        'subscribers_latest_gifter_name' => ['path' => 'subscribers.data.0.gifter_name', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Gifter Name', 'desc' => 'Latest gifter display name', 'sample' => ''],
        'subscribers_latest_broadcaster_id' => ['path' => 'subscribers.data.0.broadcaster_id', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Broadcaster ID', 'desc' => 'Broadcaster ID on the latest subscription', 'sample' => '123456789'],
        'subscribers_latest_broadcaster_login' => ['path' => 'subscribers.data.0.broadcaster_login', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Broadcaster Login', 'desc' => 'Broadcaster login on the latest subscription', 'sample' => 'streamername'],
        'subscribers_latest_broadcaster_name' => ['path' => 'subscribers.data.0.broadcaster_name', 'category' => 'subscribers', 'type' => 'string', 'label' => 'Latest Broadcaster Name', 'desc' => 'Broadcaster name on the latest subscription', 'sample' => 'StreamerName'],

        // ---- Goals (same as subscribers: empty rather than withheld) ----
        'goals_latest_type' => ['path' => 'goals.data.0.type', 'category' => 'goals', 'type' => 'string', 'label' => 'Latest Type', 'desc' => 'Latest goal type', 'sample' => 'follower'],
        'goals_latest_target' => ['path' => 'goals.data.0.target', 'category' => 'goals', 'type' => 'integer', 'label' => 'Latest Target', 'desc' => 'Latest goal target amount', 'sample' => 2000],
        'goals_latest_current' => ['path' => 'goals.data.0.current', 'category' => 'goals', 'type' => 'integer', 'label' => 'Latest Current', 'desc' => 'Latest goal current amount', 'sample' => 1234],
        'goals_latest_description' => ['path' => 'goals.data.0.description', 'category' => 'goals', 'type' => 'string', 'label' => 'Latest Description', 'desc' => 'Latest goal description', 'sample' => 'Road to 2K followers!'],
        'goals_latest_created_at' => ['path' => 'goals.data.0.created_at', 'category' => 'goals', 'type' => 'datetime', 'label' => 'Latest Created At', 'desc' => 'When the latest goal was created', 'sample' => 1777075200],
    ];

    /**
     * Category metadata. `sort_order` is the array order.
     */
    private const array TAG_CATEGORY_META = [
        'user' => ['display_name' => 'User Information', 'description' => 'The most recent user who triggered an event - not your own account'],
        'channel' => ['display_name' => 'Channel Information', 'description' => 'Your channel settings and current stream info'],
        'followers' => ['display_name' => 'Followers', 'description' => 'Follower statistics and latest follower info'],
        'followed' => ['display_name' => 'Followed Channels', 'description' => 'Channels that you follow'],
        'subscribers' => ['display_name' => 'Subscribers', 'description' => 'Subscriber statistics and latest subscriber info'],
        'goals' => ['display_name' => 'Goals', 'description' => 'Channel goals and progress'],
        'overlay' => ['display_name' => 'Overlay Metadata', 'description' => 'Information about the overlay itself'],
        'event' => ['display_name' => 'Event Data', 'description' => 'Dynamic event data from Twitch EventSub'],
    ];

    /**
     * Tags the mapper computes rather than reading from the Twitch payload.
     * They are emitted at render time by mapTwitchDataForTemplates() and are
     * listed here so the tag browser can show them; they have no `path`.
     */
    private const array COMPUTED_TAGS = [
        'overlay_name' => ['category' => 'overlay', 'type' => 'string', 'label' => 'Overlay Name', 'desc' => 'Name of the overlay', 'sample' => 'My Awesome Overlay'],
        'timestamp' => ['category' => 'overlay', 'type' => 'datetime', 'label' => 'Timestamp', 'desc' => 'Current timestamp', 'sample' => '2026-08-20 12:00:00'],
    ];

    /**
     * The full catalogue including computed tags, keyed by tag name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tagCatalog(): array
    {
        return array_merge(self::TAG_CATALOG, self::COMPUTED_TAGS);
    }

    /**
     * @return array<string, array{display_name: string, description: string}>
     */
    public static function tagCategoryMeta(): array
    {
        return self::TAG_CATEGORY_META;
    }

    /**
     * MASTER MAPPING - derived from TAG_CATALOG.
     * Format: 'json_path' => 'template_tag_name'
     */
    private function getTemplateMappings(): array
    {
        $mappings = [];

        foreach (self::TAG_CATALOG as $tagName => $spec) {
            $mappings[$spec['path']] = $tagName;
        }

        return $mappings;
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

        // Email is no longer stored. Tag preserved so legacy templates still
        // render, but always resolves to the same placeholder.
        $templateData['user_email'] = 'disabled@for-your-security';

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
     * Get sample data for template previews and testing.
     * Derived from TAG_CATALOG - see the docblock there.
     */
    public function getSampleTemplateData(): array
    {
        $samples = [];

        foreach (self::tagCatalog() as $tagName => $spec) {
            $samples[$tagName] = $spec['sample'];
        }

        return $samples;
    }

    /**
     * Get a list of all available template tags with descriptions.
     * Derived from TAG_CATALOG, plus the event.* tags the mapper emits at
     * render time from an EventSub payload.
     */
    public function getAvailableTemplateTags(): array
    {
        $descriptions = [];

        foreach (self::tagCatalog() as $tagName => $spec) {
            $descriptions[$tagName] = $spec['desc'];
        }

        return $descriptions;
    }

    /**
     * Get category mappings for organising template tags.
     * Derived from TAG_CATALOG + TAG_CATEGORY_META, so a category can never
     * advertise a tag the mapping does not produce.
     */
    public function getTagCategories(): array
    {
        $categories = [];

        foreach (self::TAG_CATEGORY_META as $key => $meta) {
            $categories[$key] = $meta + ['tags' => []];
        }

        foreach (self::tagCatalog() as $tagName => $spec) {
            $categories[$spec['category']]['tags'][] = $tagName;
        }

        // event.* tags are produced from the EventSub payload at render time,
        // not from the Twitch data snapshot, so they have no catalogue entry.
        $categories['event']['tags'] = self::EVENT_TAGS;

        return $categories;
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

        // If a tag allowlist is provided, prune to only those keys.
        // Exception: foreach iterables - extractForeachTags always emits
        // `<iterable>.count`, so its presence is a reliable signal that the
        // template loops over `<iterable>`. Keep all `<iterable>.*` keys in
        // that case so [[[raw]]] inside the loop has the full item shape,
        // not just the subkeys the body happened to reference by name.
        if (is_array($templateTags) && count($templateTags)) {
            $allowedKeys = array_flip($templateTags);
            $iterablePrefixes = [];
            foreach ($templateTags as $tag) {
                if (is_string($tag) && str_ends_with($tag, '.count')) {
                    $iterablePrefixes[] = substr($tag, 0, -strlen('count'));
                }
            }

            if (empty($iterablePrefixes)) {
                return array_intersect_key($all, $allowedKeys);
            }

            $pruned = [];
            foreach ($all as $key => $value) {
                if (isset($allowedKeys[$key])) {
                    $pruned[$key] = $value;

                    continue;
                }
                foreach ($iterablePrefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        $pruned[$key] = $value;
                        break;
                    }
                }
            }

            return $pruned;
        }

        return $all;
    }

    /**
     * Public read of a dot path in a Twitch payload, so tag seeding can resolve
     * a catalogue entry's real value without duplicating the traversal rules.
     */
    public function valueAtPath(array $data, string $path): mixed
    {
        return $this->getNestedValue($data, $path);
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
            if (! is_string($value) || $value === '') {
                return '';
            }

            try {
                return Carbon::parse($value)->getTimestamp();
            } catch (Exception) {
                return $value;
            }
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
