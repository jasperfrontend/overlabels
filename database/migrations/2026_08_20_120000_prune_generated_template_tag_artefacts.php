<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete the tag rows the old JSON-walking generator invented.
 *
 * It named tags after whatever paths it found in the Twitch payload, so every
 * account accumulated artefacts: `channel` and `user` (the raw objects),
 * `channel_count` (a count of the KEYS on the channel object), the
 * `*_pagination_cursor` entries, and `channel_content_classification_labels_0`.
 * None of them resolve to anything a template can use. CleanupRedundantTags
 * only ever matched the `_data_\d` subset and has been deleted along with the
 * walker.
 *
 * Additive backfill is deliberately NOT done here. New rows want live sample
 * values, which means a Twitch call this migration cannot make - so the tags a
 * user gains (channel_tags, channel_tags_5-9, followed_latest_*) arrive on the
 * next run of "Refresh Tags" on /tags, which is now always available and
 * idempotent. This migration only removes what should never have existed.
 *
 * The allowlist is frozen as a literal on purpose: a migration is dated but a
 * reference to App\Services\TemplateDataMapperService is not, so reading the
 * catalogue here would silently re-scope this migration every time the
 * catalogue changes.
 */
return new class extends Migration
{
    /**
     * The catalogue as of 2026-08-20. Do not update this list - a later
     * catalogue change gets its own migration if it needs one.
     */
    private const array VALID_TAG_NAMES = [
        'channel_content_labels', 'channel_delay', 'channel_game', 'channel_game_id', 'channel_id',
        'channel_is_branded', 'channel_language', 'channel_login', 'channel_name', 'channel_tags', 'channel_tags_0',
        'channel_tags_1', 'channel_tags_2', 'channel_tags_3', 'channel_tags_4', 'channel_tags_5', 'channel_tags_6',
        'channel_tags_7', 'channel_tags_8', 'channel_tags_9', 'channel_title', 'followed_latest_date',
        'followed_latest_id', 'followed_latest_login', 'followed_latest_name', 'followed_total',
        'followers_latest_date', 'followers_latest_user_id', 'followers_latest_user_login',
        'followers_latest_user_name', 'followers_total', 'goals_latest_created_at', 'goals_latest_current',
        'goals_latest_description', 'goals_latest_target', 'goals_latest_type', 'overlay_name',
        'subscribers_latest_broadcaster_id', 'subscribers_latest_broadcaster_login',
        'subscribers_latest_broadcaster_name', 'subscribers_latest_gifter_id', 'subscribers_latest_gifter_login',
        'subscribers_latest_gifter_name', 'subscribers_latest_is_gift', 'subscribers_latest_plan_name',
        'subscribers_latest_tier', 'subscribers_latest_user_id', 'subscribers_latest_user_login',
        'subscribers_latest_user_name', 'subscribers_points', 'subscribers_total', 'timestamp', 'user_avatar',
        'user_broadcaster_type', 'user_created', 'user_description', 'user_email', 'user_id', 'user_login',
        'user_name', 'user_offline_banner', 'user_type', 'user_view_count',
    ];

    public function up(): void
    {
        // Only rows the generator produced. tag_type is 'standard' for every
        // one of those; the 'custom' scope on the model exists for hand-authored
        // tags, and those stay whatever the user made them.
        DB::table('template_tags')
            ->whereNotIn('tag_name', self::VALID_TAG_NAMES)
            ->where('tag_type', 'standard')
            ->delete();

        // Categories whose every tag just went.
        DB::table('template_tag_categories')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('template_tags')
                    ->whereColumn('template_tags.category_id', 'template_tag_categories.id');
            })
            ->delete();
    }

    public function down(): void
    {
        // Nothing to restore: these rows described paths that resolve to
        // nothing, and re-inventing them would mean reinstating the walker.
    }
};
